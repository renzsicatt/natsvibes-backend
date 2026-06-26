<?php

namespace Database\Seeders;

use App\Models\Hangout;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use App\Models\VenueTag;
use App\Models\VibeTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign keys checks to truncate tables safely
        Schema::disableForeignKeyConstraints();

        User::truncate();
        Profile::truncate();
        Venue::truncate();
        VenuePhoto::truncate();
        VenueTag::truncate();
        VibeTag::truncate();
        Hangout::truncate();
        Report::truncate();

        Schema::enableForeignKeyConstraints();

        // 1. Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@natsvibe.com',
            'role' => 'admin',
            'status' => 'active',
            'date_of_birth' => '1990-01-01',
            'password' => Hash::make('password'),
        ]);

        Profile::create([
            'user_id' => $admin->id,
            'name' => 'Admin User',
            'age' => 30,
            'city' => 'Manila',
            'bio' => 'NatsVibe System Administrator',
            'completion_status' => 'completed',
            'verification_status' => 'approved',
        ]);

        // 2. Create host users
        $hostsData = [
            [
                'name' => 'Nika',
                'email' => 'nika@natsvibe.com',
                'age' => 22,
                'city' => 'Makati',
                'bio' => 'Always down for a glass of red wine.',
                'avatar_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=150&q=80',
            ],
            [
                'name' => 'Carlo',
                'email' => 'carlo@natsvibe.com',
                'age' => 25,
                'city' => 'BGC',
                'bio' => 'Rooftop sunset enthusiast.',
                'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80',
            ],
            [
                'name' => 'Lara',
                'email' => 'lara@natsvibe.com',
                'age' => 23,
                'city' => 'Manila',
                'bio' => 'Karaoke night organizer.',
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80',
            ],
            [
                'name' => 'Renz',
                'email' => 'renz@natsvibe.com',
                'age' => 24,
                'city' => 'Makati',
                'bio' => 'Electronic music and craft cocktail enthusiast.',
                'avatar_url' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
            ],
        ];

        $hosts = [];
        foreach ($hostsData as $hData) {
            $user = User::create([
                'name' => $hData['name'],
                'email' => $hData['email'],
                'role' => 'host',
                'status' => 'active',
                'date_of_birth' => now()->subYears($hData['age'])->toDateString(),
                'password' => Hash::make('password'),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'name' => $hData['name'],
                'age' => $hData['age'],
                'city' => $hData['city'],
                'bio' => $hData['bio'],
                'avatar_url' => $hData['avatar_url'],
                'completion_status' => 'completed',
                'verification_status' => 'approved',
                'photo_review_status' => 'approved',
                'host_verification_status' => 'approved',
            ]);

            $hosts[$hData['name']] = $user;
        }

        // 3. Create users pending verification
        $pendingUsers = [
            [
                'name' => 'Chloe Santiago',
                'email' => 'chloe@natsvibe.com',
                'age' => 22,
                'city' => 'Makati',
                'bio' => 'Vibing, chill drinks & friendly talks.',
                'avatar_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80',
            ],
            [
                'name' => 'James Reyes',
                'email' => 'james@natsvibe.com',
                'age' => 25,
                'city' => 'BGC',
                'bio' => 'Rooftop sunset enthusiast and tech guy.',
                'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80',
            ],
            [
                'name' => 'Sarah Lim',
                'email' => 'sarah@natsvibe.com',
                'age' => 23,
                'city' => 'Manila',
                'bio' => 'Always down for a singing/karaoke session.',
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80',
            ],
        ];

        $seededPending = [];
        foreach ($pendingUsers as $pData) {
            $user = User::create([
                'name' => $pData['name'],
                'email' => $pData['email'],
                'status' => 'pending_verification',
                'date_of_birth' => now()->subYears($pData['age'])->toDateString(),
                'password' => Hash::make('password'),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'name' => $pData['name'],
                'age' => $pData['age'],
                'city' => $pData['city'],
                'bio' => $pData['bio'],
                'avatar_url' => $pData['avatar_url'],
                'completion_status' => 'completed',
                'verification_status' => 'pending',
            ]);

            $seededPending[$pData['name']] = $user;
        }

        // Create reported spam users
        $reportedUsers = [
            [
                'name' => 'MarkSpam99',
                'email' => 'markspam@natsvibe.com',
                'bio' => 'Just here for fun',
                'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=150&q=80',
            ],
            [
                'name' => 'AlexDancer',
                'email' => 'alexdancer@natsvibe.com',
                'bio' => 'Techno lover',
                'avatar_url' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=150&q=80',
            ],
            [
                'name' => 'Sofia Go',
                'email' => 'sofiago@natsvibe.com',
                'bio' => 'Art curator',
                'avatar_url' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=150&q=80',
            ],
        ];

        $seededReported = [];
        foreach ($reportedUsers as $rData) {
            $user = User::create([
                'name' => $rData['name'],
                'email' => $rData['email'],
                'status' => 'active',
                'date_of_birth' => '1995-01-01',
                'password' => Hash::make('password'),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'name' => $rData['name'],
                'bio' => $rData['bio'],
                'avatar_url' => $rData['avatar_url'],
                'completion_status' => 'completed',
                'verification_status' => 'approved',
            ]);

            $seededReported[$rData['name']] = $user;
        }

        // 4. Create vibe tags directory
        $vibeTags = [
            'Chill drinks',
            'Rooftop',
            'Live music',
            'Karaoke',
            'First-timer',
            'Introvert-friendly',
            'Singles',
            'Budget-friendly',
        ];
        foreach ($vibeTags as $tag) {
            VibeTag::create([
                'name' => $tag,
                'slug' => Str::slug($tag),
            ]);
        }

        // 5. Create venues
        $venue1 = Venue::create([
            'name' => 'Lowlight Wine Room',
            'area' => 'Poblacion',
            'address' => '4991 P. Guanzon, Makati, 1210 Metro Manila',
            'maps_link' => 'https://maps.app.goo.gl/abcdefg',
            'venue_type' => 'Wine Bar',
            'price_range' => '$$',
            'reservation_required' => false,
            'status' => 'active',
        ]);

        $venue2 = Venue::create([
            'name' => 'Rooftop Social',
            'area' => 'BGC',
            'address' => '30th St, Taguig, Metro Manila',
            'maps_link' => 'https://maps.app.goo.gl/hijklmn',
            'venue_type' => 'Rooftop',
            'price_range' => '$$$',
            'reservation_required' => true,
            'status' => 'active',
        ]);

        $venue3 = Venue::create([
            'name' => 'Karaoke Room 88',
            'area' => 'Makati',
            'address' => 'Valero Street, Salcedo Village, Makati',
            'maps_link' => 'https://maps.app.goo.gl/opqrstu',
            'venue_type' => 'Karaoke',
            'price_range' => '$',
            'reservation_required' => false,
            'status' => 'active',
        ]);

        VenuePhoto::create([
            'venue_id' => $venue1->id,
            'photo_url' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?auto=format&fit=crop&w=600&q=80',
            'is_primary' => true,
        ]);

        VenuePhoto::create([
            'venue_id' => $venue2->id,
            'photo_url' => 'https://images.unsplash.com/photo-1533777857889-4be7c70b33f7?auto=format&fit=crop&w=600&q=80',
            'is_primary' => true,
        ]);

        VenuePhoto::create([
            'venue_id' => $venue3->id,
            'photo_url' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=600&q=80',
            'is_primary' => true,
        ]);

        // Create venue tags mappings
        $tagTalk = VenueTag::create(['name' => 'Talk-friendly', 'slug' => 'talk-friendly']);
        $tagChill = VenueTag::create(['name' => 'Chill', 'slug' => 'chill']);
        $tagWine = VenueTag::create(['name' => 'Wine', 'slug' => 'wine']);
        $tagSkyline = VenueTag::create(['name' => 'Skyline View', 'slug' => 'skyline-view']);
        $tagFeatured = VenueTag::create(['name' => 'Featured', 'slug' => 'featured']);
        $tagResReady = VenueTag::create(['name' => 'Reservation ready', 'slug' => 'reservation-ready']);
        $tagSinging = VenueTag::create(['name' => 'Singing', 'slug' => 'singing']);
        $tagBudget = VenueTag::create(['name' => 'Budget-friendly', 'slug' => 'budget-friendly']);
        $tagPrivate = VenueTag::create(['name' => 'Private rooms', 'slug' => 'private-rooms']);

        // Sync tags
        $venue1->tags()->sync([$tagTalk->id, $tagChill->id, $tagWine->id]);
        $venue2->tags()->sync([$tagFeatured->id, $tagResReady->id, $tagSkyline->id]);
        $venue3->tags()->sync([$tagPrivate->id, $tagBudget->id, $tagSinging->id]);

        // 6. Create hangouts
        $hangout1 = Hangout::create([
            'host_id' => $hosts['Nika']->id,
            'venue_id' => $venue1->id,
            'title' => 'Poblacion chill table',
            'description' => "Let's gather for a nice talk and some wine.",
            'date_time' => '2026-06-26 21:00:00',
            'area' => 'Poblacion',
            'group_size_limit' => 6,
            'budget_range' => '$$',
            'status' => 'open',
        ]);

        $hangout2 = Hangout::create([
            'host_id' => $hosts['Carlo']->id,
            'venue_id' => $venue2->id,
            'title' => 'BGC rooftop social',
            'description' => 'Sunset drinks at the rooftop!',
            'date_time' => '2026-06-27 20:30:00',
            'area' => 'BGC',
            'group_size_limit' => 5,
            'budget_range' => '$$$',
            'status' => 'open',
        ]);

        Hangout::create([
            'host_id' => $hosts['Lara']->id,
            'venue_id' => $venue3->id,
            'title' => 'Karaoke first-timers',
            'description' => 'Sing your heart out, no judgment here.',
            'date_time' => '2026-06-27 22:00:00',
            'area' => 'Makati',
            'group_size_limit' => 8,
            'budget_range' => '$',
            'status' => 'open',
        ]);

        // 7. Create user reports
        Report::create([
            'reporter_id' => $seededPending['James Reyes']->id,
            'reported_user_id' => $seededReported['MarkSpam99']->id,
            'reported_hangout_id' => $hangout1->id,
            'reason' => 'Spamming Links',
            'details' => 'Sent telegram links to crypto channels in the group chat multiple times.',
            'status' => 'pending',
        ]);

        Report::create([
            'reporter_id' => $seededReported['Sofia Go']->id,
            'reported_user_id' => $seededReported['AlexDancer']->id,
            'reported_hangout_id' => $hangout2->id,
            'reason' => 'No-Show / Unresponsive',
            'details' => 'Host approved request, but user did not attend and blocked messages on meet up.',
            'status' => 'pending',
        ]);
    }
}
