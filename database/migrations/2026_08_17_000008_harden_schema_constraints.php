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

    /**
     * Hardcoded copy / drop / rename statements for each table rebuild step.
     *
     * SQL identifiers (table names) cannot be parameterised with placeholders,
     * so the only way to eliminate string interpolation from DDL is to inline
     * every literal here.  No variable is ever interpolated into a SQL string;
     * each statement is a plain string constant that the scanner can verify
     * statically.  Each entry maps table → [copy, drop, rename].
     */
    private const TABLE_STEPS = [
        'debates' => [
            'INSERT INTO debates_new SELECT * FROM debates',
            'DROP TABLE debates',
            'ALTER TABLE debates_new RENAME TO debates',
        ],
        'rounds' => [
            'INSERT INTO rounds_new SELECT * FROM rounds',
            'DROP TABLE rounds',
            'ALTER TABLE rounds_new RENAME TO rounds',
        ],
        'turns' => [
            'INSERT INTO turns_new SELECT * FROM turns',
            'DROP TABLE turns',
            'ALTER TABLE turns_new RENAME TO turns',
        ],
        'turn_rewrites' => [
            'INSERT INTO turn_rewrites_new SELECT * FROM turn_rewrites',
            'DROP TABLE turn_rewrites',
            'ALTER TABLE turn_rewrites_new RENAME TO turn_rewrites',
        ],
        'adjudications' => [
            'INSERT INTO adjudications_new SELECT * FROM adjudications',
            'DROP TABLE adjudications',
            'ALTER TABLE adjudications_new RENAME TO adjudications',
        ],
    ];

    public function up(): void
    {
        // SQLite migrations are not wrapped in a transaction, so PRAGMA
        // foreign_keys can be toggled safely while tables are rebuilt.
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            foreach (self::TABLES as $table => $createSql) {
                // Create the shadow table with the new constraints.
                DB::statement($createSql);

                // Execute copy / drop / rename using only hardcoded string
                // literals from TABLE_STEPS — no variable is interpolated
                // into any SQL string (resolves SQL-injection finding).
                foreach (self::TABLE_STEPS[$table] as $step) {
                    DB::statement($step);
                }
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
