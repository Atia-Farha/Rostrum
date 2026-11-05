<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    */
    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs
    |--------------------------------------------------------------------------
    */
    'elevenlabs_model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),

    /*
    |--------------------------------------------------------------------------
    | TTS Max Characters
    | The AI transcript is trimmed to this many characters (at a sentence
    | boundary) before it is sent to ElevenLabs. The full text is still shown
    | in the transcript. When the account has few credits left, the request
    | is automatically retried with an even shorter text that fits the
    | remaining quota — so a debate never runs completely silent.
    |--------------------------------------------------------------------------
    */
    'tts_max_chars' => (int) env('ELEVENLABS_TTS_MAX_CHARS', 1500),

    /*
    |--------------------------------------------------------------------------
    | Phase Time Limits (seconds)
    |--------------------------------------------------------------------------
    */
    'phase_duration' => [
        'opening'  => 180, // 3 min
        'rebuttal' => 120, // 2 min
        'closing'  => 120, // 2 min
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Word Count Ranges
    |--------------------------------------------------------------------------
    | Length is driven by the phase AND by the speaking slot within it.
    |
    | The phase keys are the fallback. The slot keys ("opening.second" and
    | so on) are what actually get used, because the second speaker of a
    | phase is never delivering the same speech as the first: in Opening,
    | the first speaker builds the case and the second speaker answers it.
    | A second speaker given the opening's word budget produces a rebuttal
    | with the length of an opening speech, and a rebuttal that overruns
    | its phase is the one mistake the timer cannot recover from.
    */
    'ai_word_count' => [
        // Phase-level fallbacks.
        'opening'  => '150–220',
        'rebuttal' => '100–160',
        'closing'  => '100–160',
        'sparring' => '100–160',

        // Slot-level, keyed "{phase}.{first|second}".
        'opening.first'   => '150–220',
        'opening.second'  => '110–170',
        'rebuttal.first'  => '110–170',
        'rebuttal.second' => '100–160',
        'closing.first'   => '100–160',
        'closing.second'  => '90–140',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debate Register
    |--------------------------------------------------------------------------
    | The shared directive that makes an AI speech read like a speech
    | rather than like an essay.
    |
    | The persona prompt says who the speaker is and the difficulty
    | fragment says how strong they are. Neither of those says anything
    | about the register, and a model with no register instruction will
    | write in prose paragraphs with headings — which is not what a
    | debater does at a podium, and which the TTS voice then reads aloud
    | like a report being presented to a board.
    |
    | These are the constraints that make the output speakable: it is
    | signposted so a listener can follow it, it is a chain of claim →
    | mechanism → impact, and it answers the specific thing the opponent
    | said rather than the opponent in general.
    */
    'debate_style' => [
        'register' => 'You are speaking at the podium in a live one-on-one debate round. Your speech is spoken, not written: it must sound like a debater talking to a room, not like an essay being read out. Write in full sentences as they would be spoken aloud, and cut anything that a listener would have to read twice to follow.',

        'rules' => [
            'Signpost every part of the speech out loud ("First...", "My second point...", "The crux of this round is...", "To close..."). A listener must be able to follow your case without seeing it.',
            'Every argument is a chain: the claim, the mechanism that makes it true, and the impact it has. A claim with no mechanism and no impact is not an argument, it is an assertion.',
            'Engage the opponent through what they actually said. Quote or paraphrase the specific point you are answering. Never talk about "the opposition" in the abstract when you could answer the thing they said.',
            'No headings, no bullet points, no numbered lists, no markdown, no stage directions, no quotation marks around the whole speech. This is what a debater says, not what a debater writes down.',
            'No filler ("um", "uh", "you know", "I think maybe") and no throat-clearing. Do not open with pleasantries ("Good morning", "Hello everyone", "Ladies and gentlemen") — open on your first substantive line.',
            'Do not break character, do not mention being an AI, and do not narrate what you are about to do ("I will now explain...").',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stage Profiles
    |--------------------------------------------------------------------------
    | What a speech is FOR, keyed by "{phase}.{first|second}".
    |
    | A phase is not a speech. In the Opening phase the first speaker
    | builds the case and the second speaker answers it; in the Closing
    | phase the first speaker weighs the debate and the second speaker
    | takes the last word. Both speeches are "in the closing phase", and
    | a prompt that only knows the phase gives both of them the same
    | job — which is how a closing speech ends up introducing new
    | arguments, or how a rebuttal re-delivers an opening.
    |
    | Each profile names the role the speech plays, the job it has to
    | do, and the constraints that keep it inside the speech it is
    | meant to be. The job is written in the language of the format:
    | rebut, extend, undercut, weigh, compare impact.
    */
    'stage_profiles' => [
        'opening.first' => [
            'role' => 'Opening Speech',
            'job' => 'This is your constructive opening speech. Build your case from the ground up: state the framework and the principles your side stands on, define or scope the motion in your favour if the wording is contestable, and then present two or three fully developed arguments. Every argument states the claim, the mechanism that makes it true, and the impact it has. You are the first speaker of the round — there is no opposing speech to answer, so do not attempt to rebut anything.',
            'constraints' => [
                'Open with your framework within the first two sentences. No warm-up, no greeting.',
                'Deliver two or three arguments, each with its own signpost, its own mechanism and its own impact. Three thin arguments are worse than two that land.',
                'Do not refer to anything the opponent has said — they have not spoken yet.',
                'End by naming the clash you have set up, because you will come back to it in this round.',
            ],
        ],
        'opening.second' => [
            'role' => 'Reply to the Opening',
            'job' => 'This is your reply speech. The opponent has just delivered their opening speech, and your job is to answer it — not to restate your own opening. Take their arguments in the order they made them and answer each one directly. On their strongest point, do not merely deny it: extend it (show it is true but for their reasons, so it proves your case instead), undercut it (attack the premise it rests on), or rebut it (show the inference does not follow). On their weakest point, name the weakness and press it. Then re-anchor your own case in a sentence or two.',
            'constraints' => [
                'Answer their arguments in the order they made them, and say which one you are answering ("My answer to their first point...").',
                'Name the weakness precisely — the premise, the evidence, or the inference. "Your argument is unconvincing" is not an answer.',
                'Do not introduce a new case, and do not repeat your own opening argument as though it were new.',
                'You may quote a short phrase of theirs verbatim and respond to it directly.',
            ],
        ],
        'rebuttal.first' => [
            'role' => 'Rebuttal Speech',
            'job' => 'This is your rebuttal speech. The opponent\'s last speech is still the claim you have to answer, and your advantage in the opening is what you press. Take the point they have not yet answered and finish it. Where they have answered one of your arguments, press the weakness in their answer rather than restarting the argument. This phase is where the round is decided, so precision beats breadth.',
            'constraints' => [
                'Answer the opponent\'s most recent speech directly and say which point you are picking up.',
                'Do not re-deliver your opening. Build on it, finish it, or press their reply.',
                'Every new sentence of ground must be aimed at something they actually said.',
                'No new constructive arguments — this phase is clash, not construction.',
            ],
        ],
        'rebuttal.second' => [
            'role' => 'Final Rebuttal',
            'job' => 'This is your final rebuttal. The opponent has just rebutted you, so answer that rebuttal point by point and then resolve the clash between you. Take each of their points, expose the weakest premise or the clearest logical gap, and say plainly whether their answer to your argument succeeded. Then state which reading of the round\'s main question your side prefers, and why — and leave the closing speech the ground on which it will be weighed.',
            'constraints' => [
                'Answer their rebuttal in the order they made it.',
                'Say which of your earlier arguments they have actually answered and which they have evaded.',
                'Do not introduce new arguments and do not repeat your own rebuttal.',
                'End by naming the clash you want the closing to be decided on.',
            ],
        ],
        'closing.first' => [
            'role' => 'Closing Speech',
            'job' => 'This is your closing speech, and you weigh the debate rather than build it. Introduce no new arguments — every point you make must be one the debate has already raised. Take the round\'s core clash, say clearly who has answered it better and why, and compare the impacts of the arguments that survive: which one actually happens, and how much it matters. Tell the judge what the round came down to and why your side won it.',
            'constraints' => [
                'No new arguments, no new evidence, no new examples.',
                'Weigh explicitly: "Their best point was X. Our answer to it was Y, and that answer is stronger because...".',
                'Compare impact, not the number of arguments. Saying which argument matters more is the whole job of a closing.',
                'State the clash and your verdict on it plainly.',
            ],
        ],
        'closing.second' => [
            'role' => 'Final Reply',
            'job' => 'This is the final reply speech, and you have the last word of the round. Weigh briefly — do not re-run your closing speech — then answer the opponent\'s closing directly, defeat the strongest claim in it, and end the debate: say what the round was about, why your side wins it, and stop.',
            'constraints' => [
                'Keep the weighing short. The fight is with their closing speech, not with the whole round again.',
                'Answer their closing point by point and defeat their strongest claim in it.',
                'You have the last word — end the debate in one or two sentences, decisively.',
                'No new arguments.',
            ],
        ],
        'sparring' => [
            'role' => 'Sparring Exchange',
            'job' => 'This is a sparring round, not a set piece. There are no phases and no fixed speeches — you pick the move that the exchange calls for. Review what has been said, choose exactly one move type, and play it: a follow-up question probing a claim they have not supported, a contradiction callout if they have contradicted themselves, a live challenge ("give me one piece of evidence for that"), or a standard rebuttal of their strongest point. Open your reply with the tag for the move you are making — [FOLLOW-UP], [CONTRADICTION], [CHALLENGE] or [REBUTTAL] — so the reader can see what you did.',
            'constraints' => [
                'Exactly one move per reply, and it must be the move the transcript actually calls for. Do not announce a move and then deliver another.',
                'Keep it short and conversational — this is a trade, not a speech.',
                'Do not break character or mention being an AI. Do not use markdown formatting.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Adjudication Rubric
    |--------------------------------------------------------------------------
    | The three criteria the judge scores against, and what each one is
    | looking at. This is the single source of truth for the rubric: the
    | Gemini prompt is built from it, and the report renders it. A report
    | that shows a rubric the judge never used is a document that lies
    | about its own method, so the two cannot be separate copies.
    |
    | `criteria` carries the criteria themselves — the key matches the
    | column on the adjudications table, the label is what the reader
    | sees, and `weight` is the maximum the criterion can score.
    |
    | `bands` is the descriptive ladder under each criterion. It is what
    | turns a number into a judgement: "31 / 40" is a mark, but "31 / 40
    | — strong, with a weak mechanism" is something a debater can act on.
    | Each band names the lowest score that falls in it and the
    | descriptor that goes with it. The bands are printed with the
    | report and the one the speaker landed in is marked.
    */
    'rubric' => [
        'criteria' => [
            'matter' => [
                'label'    => 'Matter',
                'question' => 'Quality, relevance and logical soundness of arguments and evidence',
                'weight'   => 40,
                'bands'    => [
                    ['min' => 34, 'label' => 'Excellent',   'note' => 'Well-evidenced, causally sound and directly on the motion.'],
                    ['min' => 27, 'label' => 'Solid',       'note' => 'Substantive argument with real evidence; the reasoning largely holds.'],
                    ['min' => 20, 'label' => 'Adequate',    'note' => 'The case is made, but links or evidence are thin in places.'],
                    ['min' => 12, 'label' => 'Thin',        'note' => 'The argument is understandable but the reasoning is loose.'],
                    ['min' => 0,  'label' => 'Not made',    'note' => 'Claims are asserted rather than argued, or they miss the motion.'],
                ],
            ],
            'manner' => [
                'label'    => 'Manner',
                'question' => 'Persuasiveness, clarity and rhetorical delivery',
                'weight'   => 30,
                'bands'    => [
                    ['min' => 26, 'label' => 'Excellent',   'note' => 'Commanding and clear; the framing carries the speech.'],
                    ['min' => 21, 'label' => 'Solid',       'note' => 'Confident and readable, with a consistent through-line.'],
                    ['min' => 15, 'label' => 'Adequate',    'note' => 'Clear enough to follow, though the delivery is flat in places.'],
                    ['min' => 9,  'label' => 'Thin',        'note' => 'The argument is hard to follow or the tone undercuts the case.'],
                    ['min' => 0,  'label' => 'Not made',    'note' => 'The speech does not hold the room.'],
                ],
            ],
            'method' => [
                'label'    => 'Method',
                'question' => 'Structure, organisation and how directly the speaker rebutted',
                'weight'   => 30,
                'bands'    => [
                    ['min' => 26, 'label' => 'Excellent',   'note' => 'Structured, signposted and every point answers the opponent.'],
                    ['min' => 21, 'label' => 'Solid',       'note' => 'Well ordered, with the rebuttals landing where they should.'],
                    ['min' => 15, 'label' => 'Adequate',    'note' => 'The shape is sound but the case is not pushed far enough.'],
                    ['min' => 9,  'label' => 'Thin',        'note' => 'Structure is inconsistent; the opponent’s points go unanswered.'],
                    ['min' => 0,  'label' => 'Not made',    'note' => 'No recognisable structure, and the case is never engaged with.'],
                ],
            ],
        ],

        /*
        | The grade bands. A judge does not only give a mark — they give a
        | mark and a word for it, and the word is what a debater repeats
        | to themselves before the next round. `min` is the lowest total
        | score that earns the label.
        |
        | The labels are deliberately ordinary words. "Distinction" and
        | "Score" are the vocabulary of the competition, but a reader
        | looking at "77 — Score" reads "Score" as a verb and cannot
        | tell what the mark means. These are the names of the bands
        | themselves, and the summary says what each one is worth.
        |
        | They must not collide with the criterion bands above. A
        | report that shows "Strong" for the overall mark and "Strong"
        | for a criterion is showing two different scales in one word,
        | and the reader has to guess which is which. The criterion
        | bands therefore use their own names.
        */
        'grades' => [
            ['min' => 85, 'label' => 'Distinction',  'summary' => 'A tournament-ready round.'],
            ['min' => 70, 'label' => 'Strong',       'summary' => 'A competent, competitive round.'],
            ['min' => 55, 'label' => 'Pass',         'summary' => 'The case stands, with clear room to improve.'],
            ['min' => 0,  'label' => 'Below Pass',   'summary' => 'The case does not yet hold together.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Speaking Rate
    |--------------------------------------------------------------------------
    | Words per minute, used to turn a transcript into an estimated
    | speaking time. A debater who wrote 300 words into a three-minute
    | opening has either left material on the table or has a delivery
    | problem, and the report should say which rather than leave it
    | implied.
    |
    | 140 is a measured average for prepared competitive debate: fast
    | enough to fill a phase, slow enough to stay comprehensible.
    */
    'words_per_minute' => 140,

    /*
    |--------------------------------------------------------------------------
    | Difficulty Prompt Fragments
    | Appended to the persona's system_prompt at call time (§6.2a, §14)
    |--------------------------------------------------------------------------
    */
    'difficulty_prompts' => [
        'beginner' => 'Argue simply and clearly, as a friendly practice partner would. Let minor weaknesses in your opponent\'s case go unchallenged so they can build confidence. Use straightforward vocabulary and avoid complex rhetorical moves.',

        'intermediate' => 'Argue at a solid club-level standard. Engage directly with your opponent\'s main points, use one or two concrete examples, and maintain a clear logical structure throughout your speech.',

        'advanced' => 'Argue at a competitive tournament standard. Use precise evidence and real-world examples. Identify and target the weakest premise in your opponent\'s case. Employ sophisticated rhetorical structures (e.g. link-turns, impact calculus). Leave no major claim by your opponent unaddressed.',

        'world_champion' => 'Argue at a tournament-champion skill level. Use precise evidence and examples. Actively identify the single weakest link in your opponent\'s most recent point and target it directly. Employ advanced debate techniques: pre-empting likely responses, framing the round\'s core clash, and making explicit impact comparisons. Your arguments should be airtight.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Motion Categories
    |--------------------------------------------------------------------------
    */
    'motion_categories' => [
        'politics',
        'technology',
        'society',
        'environment',
        'education',
        'economy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed Demo Session
    | The seeded adjudicated demo debate (TournamentModeSeeder). It is owned
    | by the demo account below and belongs to no one else: an account's
    | history is a record of that account's own practice, so the demo is
    | shown by signing in as the demo, not by being pushed into every new
    | account's ledger.
    |--------------------------------------------------------------------------
    */
    'seed_session_id' => 'seed-tournament-demo-001',

    /*
    |--------------------------------------------------------------------------
    | Seed Account
    |--------------------------------------------------------------------------
    | The initial seeded account. It owns the seeded demo debate and its
    | adjudication report, so signing in with these credentials opens an
    | account that already has a debate and a report on record. Every
    | other account starts empty.
    |
    | Override SEED_ACCOUNT_PASSWORD in the environment for production —
    | never ship the default password on a public deployment.
    |--------------------------------------------------------------------------
    */
    'seed_account_name'     => env('SEED_ACCOUNT_NAME', 'Demo Debater'),
    'seed_account_email'    => env('SEED_ACCOUNT_EMAIL', 'demo@rostrum.app'),
    'seed_account_password' => env('SEED_ACCOUNT_PASSWORD', 'rostrum-demo'),

    /*
    |--------------------------------------------------------------------------
    | Side Labels
    |--------------------------------------------------------------------------
    | The role name each side speaks under.
    |
    | A debater is not "the government" — a debater is the Prime Minister
    | or the Leader of the Opposition. Naming the role gives the AI a
    | stance to speak from, and it gives the transcript the shape of a
    | debate rather than a chat log.
    |
    | The first-person form is what the speaker uses about themselves
    | ("as the Prime Minister, I..."), and the third-person form is what
    | the transcript calls the other side when it needs to name them
    | without naming the side.
    */
    'side_labels' => [
        'government' => [
            'role'      => 'Prime Minister',
            'short'     => 'Government',
            'first'     => 'the Prime Minister',
            'self'      => 'our side',
            'opponent'  => 'the Opposition',
            'opposing'  => 'the Opposition Leader',
        ],
        'opposition' => [
            'role'      => 'Leader of the Opposition',
            'short'     => 'Opposition',
            'first'     => 'the Leader of the Opposition',
            'self'      => 'our side',
            'opponent'  => 'the Government',
            'opposing'  => 'the Prime Minister',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase Purposes
    |--------------------------------------------------------------------------
    | What each phase of the round is FOR, in a phrase.
    |
    | The AI is told which phase it is in, but a phase name alone does
    | not tell a speaker what the round is doing at that moment. "This
    | is the rebuttal phase" and "this is the part of the round where
    | the two cases are put against each other" do not produce the same
    | speech, and the second one is the one a debater would understand.
    */
    'phase_purposes' => [
        'opening'  => 'the phase in which both sides build their case from the ground up, before any clash',
        'rebuttal' => 'the phase in which the two cases are put directly against each other',
        'closing'  => 'the phase in which the round is weighed and a verdict is reached',
        'sparring' => 'a free practice exchange with no fixed phases and no fixed speeches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sparring Move Types
    |--------------------------------------------------------------------------
    */
    'sparring_move_types' => [
        'follow_up',
        'contradiction',
        'challenge',
        'rebuttal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Move Type Tags
    |--------------------------------------------------------------------------
    | The tags the model opens a sparring reply with, and the sparring
    | move each one names. The tag is stripped from the speech before it
    | is spoken, and the move is stored on the turn.
    |
    | The tag set is closed on purpose. If the model opens with a word
    | that happens to sit in square brackets, the old parser would
    | swallow it and the speech would open mid-sentence. Only these four
    | tags are recognised, so a bracket that is not one of them stays in
    | the speech where it belongs.
    */
    'move_type_tags' => [
        'FOLLOW-UP'   => 'follow_up',
        'CONTRADICTION' => 'contradiction',
        'CHALLENGE'   => 'challenge',
        'REBUTTAL'    => 'rebuttal',
    ],

];
