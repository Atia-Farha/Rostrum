<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the account owner to debates.
 *
 * `user_id` is NULLABLE and optional: the app keeps working exactly as before
 * for guests (who remain scoped by `session_id`). Once a guest signs in, the
 * debates they created in that session are claimed by their account (see
 * AuthController::claimSessionDebates), so their history follows them across
 * devices and sessions.
 *
 * Deleting an account removes its debates (cascade), which keeps the
 * one-to-many relationship honest at the database level.
 *
 * NOTE — why raw SQL instead of Schema::table():
 * SQLite cannot ALTER a table to add a foreign key, so Laravel rebuilds the
 * table. That rebuild silently DROPS the CHECK constraints and indexes that
 * the earlier `harden_schema_constraints` migration installed (this was
 * confirmed empirically — `user_side`, `mode`, `status`, `language` and
 * `difficulty` lost their enum checks, and the `session_id` index was already
 * missing). Rebuilding explicitly with raw SQL keeps every constraint intact
 * and restores the missing index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite migrations are not wrapped in a transaction, so PRAGMA
        // foreign_keys can be toggled safely while the table is rebuilt.
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::statement(<<<'SQL'
                CREATE TABLE "debates_new" (
                    "id" varchar not null,
                    "session_id" varchar not null,
                    "user_id" integer,
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
                    foreign key("user_id") references "users"("id") on delete cascade,
                    primary key ("id")
                )
                SQL);

            DB::statement('INSERT INTO "debates_new" (id, session_id, motion_id, user_side, persona_id, difficulty, mode, language, status, created_at, updated_at)
                            SELECT id, session_id, motion_id, user_side, persona_id, difficulty, mode, language, status, created_at, updated_at
                            FROM "debates"');

            DB::statement('DROP TABLE "debates"');
            DB::statement('ALTER TABLE "debates_new" RENAME TO "debates"');

            // Restore the indexes that the table rebuild drops.
            DB::statement('CREATE INDEX "debates_session_id_index" ON "debates" ("session_id")');
            DB::statement('CREATE INDEX "debates_user_id_index" ON "debates" ("user_id")');
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::statement(<<<'SQL'
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
                SQL);

            DB::statement('INSERT INTO "debates_new" (id, session_id, motion_id, user_side, persona_id, difficulty, mode, language, status, created_at, updated_at)
                            SELECT id, session_id, motion_id, user_side, persona_id, difficulty, mode, language, status, created_at, updated_at
                            FROM "debates"');

            DB::statement('DROP TABLE "debates"');
            DB::statement('ALTER TABLE "debates_new" RENAME TO "debates"');

            DB::statement('CREATE INDEX "debates_session_id_index" ON "debates" ("session_id")');
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
