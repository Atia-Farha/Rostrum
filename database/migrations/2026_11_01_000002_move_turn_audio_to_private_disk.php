<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves stored turn audio off the public disk and onto the private one.
 *
 * Audio used to be written to `storage/app/public/audio/` and served straight
 * off the web root at `/storage/audio/<file>`. That made every user's voice
 * recording world-readable to anyone who could guess the (UUID) file name,
 * which is exactly the kind of content that must not leak.
 *
 * After this migration:
 *
 *   - the file lives at `storage/app/private/audio/<file>`;
 *   - `turns.audio_path` holds the bare FILE NAME, not a URL;
 *   - the file is streamed back through `GET /debates/{debate}/audio/{file}`,
 *     which checks that the signed-in account owns the debate.
 *
 * The migration is idempotent: a file that is already on the private disk is
 * left alone, and a turn whose audio is already a bare file name is not
 * rewritten again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        DB::table('turns')
            ->whereNotNull('audio_path')
            ->orderBy('id')
            ->each(function ($turn) use ($public, $private) {
                $stored = (string) $turn->audio_path;

                // Already a bare file name — nothing to do.
                if (! str_contains($stored, '/')) {
                    return;
                }

                // The old format was `/storage/audio/<file>`.
                $file = basename($stored);

                // Move the file across if it is still on the public disk.
                if ($public->exists('audio/'.$file) && ! $private->exists('audio/'.$file)) {
                    $private->put('audio/'.$file, $public->get('audio/'.$file));
                }

                DB::table('turns')
                    ->where('id', $turn->id)
                    ->update(['audio_path' => $file]);
            });

        $this->claimOrphanedDebates();
    }

    /**
     * Give orphaned guest debates an owner.
     *
     * Debates created before accounts existed have `user_id = NULL`. Under the
     * new ownership model nobody but the owner may see or play a debate, so
     * those rows would become permanently unreachable — locked out of their
     * owner's own history with no way to open them.
     *
     * They are attached to the seeded account, which is the only account
     * guaranteed to exist. No debate is deleted, so no transcript or report is
     * ever lost.
     */
    private function claimOrphanedDebates(): void
    {
        $orphans = DB::table('debates')->whereNull('user_id')->count();

        if ($orphans === 0) {
            return;
        }

        $owner = User::where('email', config('debate.seed_account_email'))->first();

        if (! $owner) {
            // The seed account has not been created yet (it is created by the
            // seeder, which runs after migrations). Leave the rows for the
            // seeder to pick up — nothing is lost.
            return;
        }

        DB::table('debates')
            ->whereNull('user_id')
            ->update(['user_id' => $owner->id]);
    }

    public function down(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        DB::table('turns')
            ->whereNotNull('audio_path')
            ->orderBy('id')
            ->each(function ($turn) use ($public, $private) {
                $file = basename((string) $turn->audio_path);

                // Move the file back to the public disk.
                if ($private->exists('audio/'.$file) && ! $public->exists('audio/'.$file)) {
                    $public->put('audio/'.$file, $private->get('audio/'.$file));
                }

                DB::table('turns')
                    ->where('id', $turn->id)
                    ->update(['audio_path' => '/storage/audio/'.$file]);
            });
    }
};
