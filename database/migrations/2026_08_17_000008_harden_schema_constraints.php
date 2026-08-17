<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hardens the schema for the existing (SQLite) databases:
 *
 *  1. debates.language  — CHECK constraint locked to en/bn (the only
 *     supported debate languages).
 *  2. rounds / turns / turn_rewrites / adjudications — FKs now cascade on
 *     delete, so deleting a debate cleans up all children automatically.
 *
 * SQLite cannot ALTER existing columns or constraints, so each affected
 * table is rebuilt (create new → copy data → drop old → rename), with
 * foreign-key enforcement temporarily disabled.
 */
return new class extends Migration
{
    private const TABLES = [
        'debates' => <<<'SQL'
            CREATE TABLE "debates_new" (
                "id" varchar not null,
                "session_id" varchar not null,
                "motion_id" varchar not null,
                "user_side" varchar check ("user_side" in ('government', 'opposition')) not null,
                "persona_id" varchar not null,
                "difficulty" varchar check ("difficulty" in ('beginner', 'intermediate', 'advanced', 'world_champion')) not null default 'intermediate',
                "mode" varchar check ("mode" in ('tournament', 'sparring')) not null default 'tournament',
                "language" varchar check ("language" in ('en', 'bn')) not null,
                "status" varchar check ("status" in ('setup', 'in_progress', 'adjudicated')) not null default 'setup',
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("motion_id") references "motions"("id"),
                foreign key("persona_id") references "personas"("id"),
                primary key ("id")
            )
            SQL,

        'rounds' => <<<'SQL'
            CREATE TABLE "rounds_new" (
                "id" varchar not null,
                "debate_id" varchar not null,
                "phase" varchar check ("phase" in ('opening', 'rebuttal', 'closing')),
                "phase_order" integer not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("debate_id") references "debates"("id") on delete cascade,
                primary key ("id")
            )
            SQL,

        'turns' => <<<'SQL'
            CREATE TABLE "turns_new" (
                "id" varchar not null,
                "round_id" varchar not null,
                "speaker" varchar check ("speaker" in ('user', 'ai')) not null,
                "transcript" text not null,
                "audio_path" varchar,
                "ai_move_type" varchar,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("round_id") references "rounds"("id") on delete cascade,
                primary key ("id")
            )
            SQL,

        'turn_rewrites' => <<<'SQL'
            CREATE TABLE "turn_rewrites_new" (
                "id" varchar not null,
                "turn_id" varchar not null,
                "original_text" text not null,
                "rewritten_text" text not null,
                "explanation_bullets" text not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("turn_id") references "turns"("id") on delete cascade,
                primary key ("id")
            )
            SQL,

        'adjudications' => <<<'SQL'
            CREATE TABLE "adjudications_new" (
                "id" varchar not null,
                "debate_id" varchar not null,
                "matter_score" integer not null,
                "manner_score" integer not null,
                "method_score" integer not null,
                "total_score" integer not null,
                "fallacies" text not null,
                "feedback_bullets" text not null,
                "verdict" varchar not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("debate_id") references "debates"("id") on delete cascade,
                primary key ("id")
            )
            SQL,
    ];

    public function up(): void
    {
        // SQLite migrations are not wrapped in a transaction, so PRAGMA
        // foreign_keys can be toggled safely while tables are rebuilt.
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            foreach (array_keys(self::TABLES) as $table) {
                $new = "{$table}_new";
                DB::statement(self::TABLES[$table]);
                DB::statement("INSERT INTO {$new} SELECT * FROM {$table}");
                DB::statement("DROP TABLE {$table}");
                DB::statement("ALTER TABLE {$new} RENAME TO {$table}");
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        // Irreversible — the pre-existing constraint state cannot be restored
        // without losing data, and the corrected definitions are additive.
    }
};
