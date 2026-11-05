<?php

namespace Database\Seeders;

use App\Models\Debate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * SeedAccountSeeder
 *
 * Creates the initial seeded account and assigns the seeded demo debate
 * (and therefore its adjudication report) to it.
 *
 * The demo debate is created by TournamentModeSeeder. This seeder runs after
 * it and takes ownership of that debate, so the account's history is never
 * empty and the seeded report is reachable from the account dashboard.
 *
 * The credentials are read from config('debate.seed_account_*') so they can be
 * overridden per environment without touching the seeder.
 */
class SeedAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('debate.seed_account_email');
        $password = config('debate.seed_account_password');
        $name = config('debate.seed_account_name');

        if (! $email || ! $password) {
            $this->command->warn('Seed account credentials are not configured — skipping seed account.');

            return;
        }

        // firstOrCreate keeps the seeder idempotent: re-running it never
        // overwrites a password the demo user has since changed.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        // Claim the seeded demo debate so the account owns the seeded debate
        // and its adjudication report.
        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();

        if ($seedDebate) {
            $seedDebate->update(['user_id' => $user->id]);
        }

        // Adopt debates left behind by pre-account guests.
        //
        // Debates created before accounts existed have `user_id = NULL`. Under
        // the ownership model nobody but the owner may open a debate, so those
        // rows would be permanently unreachable — invisible in their owner's
        // history and locked to everyone. Attaching them to the seeded account
        // keeps them readable. Nothing is deleted, so no transcript or report
        // is ever lost.
        //
        // Only the demo account adopts them. A newly registered account
        // starts with an empty record, and the seeder runs on every boot —
        // so this must never hand a new account somebody else's debate.
        $orphans = Debate::whereNull('user_id')->count();

        if ($orphans > 0) {
            Debate::whereNull('user_id')->update(['user_id' => $user->id]);
        }

        $this->command->info('SeedAccountSeeder: Seeded account ready.');
        $this->command->info("  Account : {$user->name} <{$user->email}>");
        $this->command->info('  Debate  : '.($seedDebate ? "{$seedDebate->id} (owned by the seeded account)" : 'no seeded debate found — run TournamentModeSeeder first'));

        if ($orphans > 0) {
            $this->command->info("  Adopted : {$orphans} guest debate(s) created before accounts existed");
        }
    }
}
