<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Services\DebateRoundEngine;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    public function __construct(
        private GeminiService    $gemini,
        private DebateRoundEngine $engine,
    ) {}

    /**
     * GET /setup — render the setup screen.
     */
    public function index(Request $request)
    {
        $personas   = Persona::all();
        $categories = config('debate.motion_categories', []);

        return view('setup', compact('personas', 'categories'));
    }

    /**
     * POST /motions/generate — Generate a motion via Gemini.
     */
    public function generateMotion(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|in:en,bn',
            'category' => 'nullable|string',
        ]);

        // Use selected language directly for motion generation
        $geminiLang = $request->input('language', 'en');

        try {
            $motionText = $this->gemini->generateMotion(
                $geminiLang,
                $request->input('category')
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Motion generation failed in SetupController: ' . $e->getMessage());
            // Fallback sample motions if API quota/limit is exceeded or fails
            $fallbacks = [
                'en' => [
                    'This House believes that artificial intelligence poses an existential risk to human creativity.',
                    'This House would ban social media for users under the age of 16.',
                    'This House supports universal basic income funded by automation taxes.',
                    'This House believes that state development should take priority over individual privacy.',
                ],
                'bn' => [
                    'এই সভা মনে করে যে কৃত্রিম বুদ্ধিমত্তা মানুষের সৃজনশীলতার জন্য ঝুঁকি তৈরি করছে।',
                    'এই সভা ১৬ বছরের কম বয়সী ব্যবহারকারীদের জন্য সোশ্যাল মিডিয়া নিষিদ্ধ করবে।',
                    'এই সভা সার্বজনীন মৌলিক আয় চালুর পক্ষে।',
                    'এই সভা মনে করে যে রাষ্ট্রের উন্নয়নের জন্য ব্যক্তিগত গোপনীয়তাকে অগ্রাধিকার দেওয়া উচিত।',
                ],
            ];
            $options    = $fallbacks[$geminiLang] ?? $fallbacks['en'];
            $motionText = $options[array_rand($options)];
        }

        return response()->json(['motion' => $motionText]);
    }

    /**
     * POST /debates — Create a new debate and redirect to the debate screen.
     */
    public function createDebate(Request $request)
    {
        $request->validate([
            'motion_text' => 'required_without:motion_id|nullable|string|max:500',
            'motion_id'   => 'required_without:motion_text|nullable|uuid|exists:motions,id',
            'user_side'   => 'required|in:government,opposition,auto',
            'persona_id'  => 'required|uuid|exists:personas,id',
            'difficulty'  => 'required|in:beginner,intermediate,advanced,world_champion',
            'mode'        => 'nullable|in:tournament,sparring',
            'language'    => 'required|in:en,bn',
        ]);

        $language = $request->input('language');

        // Resolve or create the motion
        if ($request->filled('motion_id')) {
            $motion = Motion::findOrFail($request->input('motion_id'));
        } else {
            $motionText = trim($request->input('motion_text'));
            // Detect Bangla characters in manual input (\x{0980}-\x{09FF})
            $hasBangla = (bool) preg_match('/[\x{0980}-\x{09FF}]/u', $motionText);

            $motion = Motion::create([
                'id'       => (string) Str::uuid(),
                'text_en'  => (!$hasBangla && $language === 'en') ? $motionText : null,
                'text_bn'  => ($hasBangla || $language === 'bn') ? $motionText : null,
                'source'   => 'manual',
            ]);
        }

        // Resolve user_side
        $userSide = $request->input('user_side');
        if ($userSide === 'auto') {
            $userSide = collect(['government', 'opposition'])->random();
        }

        $mode = $request->input('mode', 'tournament');

        try {
            // Create the debate
            $debate = Debate::create([
                'id'         => (string) Str::uuid(),
                'session_id' => $request->session()->getId(),
                'motion_id'  => $motion->id,
                'user_side'  => $userSide,
                'persona_id' => $request->input('persona_id'),
                'difficulty' => $request->input('difficulty', 'beginner'),
                'mode'       => $mode,
                'language'   => $language,
                'status'     => 'in_progress',
            ]);

            // Seed the first round
            if ($mode === 'tournament') {
                $this->engine->seedFirstRound($debate);
            } else {
                $this->engine->seedSparringRound($debate);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Debate creation failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Could not start the debate right now. Please try again.');
        }

        return redirect()->route('debates.show', $debate->id);
    }
}
