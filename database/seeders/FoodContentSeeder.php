<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Like;
use App\Models\View;
use App\Models\Bookmark;
use App\Models\Follow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FoodContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🍔 Starting Food Content Seeder...');

        // Create food content creators
        $creators = $this->createFoodCreators();
        $this->command->info('✅ Created ' . count($creators) . ' food content creators');

        // Create regular users
        $regularUsers = $this->createRegularUsers();
        $this->command->info('✅ Created ' . count($regularUsers) . ' regular users');

        // All users combined
        $allUsers = array_merge($creators, $regularUsers);

        // Create follows between users
        $this->createFollows($creators, $regularUsers);
        $this->command->info('✅ Created follow relationships');

        // Create food videos
        $videos = $this->createFoodVideos($creators);
        $this->command->info('✅ Created ' . count($videos) . ' food videos');

        // Add engagement (likes, comments, views, bookmarks)
        $this->addEngagement($videos, $allUsers);
        $this->command->info('✅ Added engagement data');

        $this->command->info('🎉 Food Content Seeder completed successfully!');
        $this->command->info('📊 Summary:');
        $this->command->info('   - Users: ' . count($allUsers));
        $this->command->info('   - Videos: ' . count($videos));
        $this->command->info('   - Ready to use!');
    }

    private function createFoodCreators(): array
    {
        $creators = [
            [
                'name' => 'Chef Marco',
                'email' => 'marco@foodie.com',
                'bio' => '🍝 Italian cuisine expert | Pasta lover | Sharing authentic recipes from Rome',
            ],
            [
                'name' => 'Sushi Master Kenji',
                'email' => 'kenji@sushi.com',
                'bio' => '🍣 Traditional Japanese sushi chef | 15 years experience | Tokyo trained',
            ],
            [
                'name' => 'Dessert Queen Sarah',
                'email' => 'sarah@sweetlife.com',
                'bio' => '🍰 Pastry chef & baker | Sweet treats daily | Making desserts fun!',
            ],
            [
                'name' => 'Street Food Hunter',
                'email' => 'hunter@streetfood.com',
                'bio' => '🌮 Exploring street food worldwide | Authentic flavors | Food adventures',
            ],
            [
                'name' => 'Healthy Cook Rita',
                'email' => 'rita@healthyeats.com',
                'bio' => '🥗 Nutritionist & chef | Healthy recipes | Making wellness delicious',
            ],
            [
                'name' => 'BBQ King Mike',
                'email' => 'mike@bbqmaster.com',
                'bio' => '🍖 BBQ & grill specialist | Low & slow cooking | Smoke ring perfection',
            ],
            [
                'name' => 'Vegan Vibes Lisa',
                'email' => 'lisa@veganeats.com',
                'bio' => '🌱 Plant-based chef | Cruelty-free cooking | Delicious vegan recipes',
            ],
            [
                'name' => 'Asian Fusion Alex',
                'email' => 'alex@asianfusion.com',
                'bio' => '🍜 Asian fusion chef | Modern takes on classics | Flavor explosion',
            ],
        ];

        $createdUsers = [];
        foreach ($creators as $creator) {
            $createdUsers[] = User::create([
                'name' => $creator['name'],
                'email' => $creator['email'],
                'password' => Hash::make('password'),
                'bio' => $creator['bio'],
                'email_verified_at' => now(),
            ]);
        }

        return $createdUsers;
    }

    private function createRegularUsers(): array
    {
        $users = [];
        for ($i = 1; $i <= 20; $i++) {
            $users[] = User::create([
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.com',
                'password' => Hash::make('password'),
                'bio' => 'Food lover #' . $i,
                'email_verified_at' => now(),
            ]);
        }
        return $users;
    }

    private function createFollows(array $creators, array $regularUsers): void
    {
        // Regular users follow creators
        foreach ($regularUsers as $user) {
            // Each user follows 3-6 random creators
            $creatorsToFollow = fake()->randomElements($creators, rand(3, 6));
            foreach ($creatorsToFollow as $creator) {
                Follow::create([
                    'follower_id' => $user->id,
                    'following_id' => $creator->id,
                ]);
            }
        }

        // Creators follow each other
        foreach ($creators as $creator1) {
            $otherCreators = array_filter($creators, fn($c) => $c->id !== $creator1->id);
            $creatorsToFollow = fake()->randomElements($otherCreators, rand(2, 4));
            foreach ($creatorsToFollow as $creator2) {
                Follow::firstOrCreate([
                    'follower_id' => $creator1->id,
                    'following_id' => $creator2->id,
                ]);
            }
        }
    }

    private function createFoodVideos(array $creators): array
    {
        $foodContent = [
            // Italian Cuisine
            [
                'title' => 'Authentic Carbonara Recipe',
                'description' => 'Learn how to make real Roman carbonara with just 4 ingredients!',
                'tags' => ['italian', 'pasta', 'carbonara', 'cooking'],
                'thumbnail' => 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400',
            ],
            [
                'title' => 'Homemade Pizza Margherita',
                'description' => 'Perfect pizza dough and fresh mozzarella make this pizza amazing',
                'tags' => ['pizza', 'italian', 'homemade', 'dinner'],
                'thumbnail' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=400',
            ],
            [
                'title' => 'Creamy Risotto Tutorial',
                'description' => 'Master the art of making silky smooth mushroom risotto',
                'tags' => ['risotto', 'italian', 'mushroom', 'creamy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1476124369491-f51c26169318?w=400',
            ],

            // Japanese Cuisine
            [
                'title' => 'Perfect Sushi Rolls',
                'description' => 'Step by step guide to making beautiful sushi at home',
                'tags' => ['sushi', 'japanese', 'seafood', 'tutorial'],
                'thumbnail' => 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=400',
            ],
            [
                'title' => 'Ramen from Scratch',
                'description' => 'Homemade ramen broth and noodles that taste like restaurant quality',
                'tags' => ['ramen', 'japanese', 'noodles', 'soup'],
                'thumbnail' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400',
            ],
            [
                'title' => 'Crispy Tempura Guide',
                'description' => 'Light and crispy tempura batter secrets revealed',
                'tags' => ['tempura', 'japanese', 'fried', 'crispy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1541529086526-db283c563270?w=400',
            ],

            // Desserts
            [
                'title' => 'Chocolate Lava Cake',
                'description' => 'Molten chocolate center perfection in 15 minutes',
                'tags' => ['dessert', 'chocolate', 'cake', 'easy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400',
            ],
            [
                'title' => 'Classic Tiramisu',
                'description' => 'No-bake Italian dessert that everyone loves',
                'tags' => ['tiramisu', 'dessert', 'italian', 'coffee'],
                'thumbnail' => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400',
            ],
            [
                'title' => 'French Macarons Tutorial',
                'description' => 'Perfect macarons with feet - troubleshooting tips included',
                'tags' => ['macarons', 'french', 'dessert', 'baking'],
                'thumbnail' => 'https://images.unsplash.com/photo-1569864358642-9d1684040f43?w=400',
            ],

            // Street Food
            [
                'title' => 'Mexican Street Tacos',
                'description' => 'Authentic al pastor tacos like you get in Mexico City',
                'tags' => ['tacos', 'mexican', 'streetfood', 'authentic'],
                'thumbnail' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400',
            ],
            [
                'title' => 'Thai Pad Thai',
                'description' => 'Sweet, sour, and savory Thai noodles in 20 minutes',
                'tags' => ['padthai', 'thai', 'noodles', 'quick'],
                'thumbnail' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400',
            ],
            [
                'title' => 'Vietnamese Banh Mi',
                'description' => 'Crispy baguette sandwich with pickled vegetables',
                'tags' => ['banhmi', 'vietnamese', 'sandwich', 'streetfood'],
                'thumbnail' => 'https://images.unsplash.com/photo-1591814468924-caf88d1232e1?w=400',
            ],

            // Healthy Foods
            [
                'title' => 'Buddha Bowl Recipe',
                'description' => 'Colorful and nutritious grain bowl with tahini dressing',
                'tags' => ['healthy', 'vegan', 'bowl', 'nutritious'],
                'thumbnail' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400',
            ],
            [
                'title' => 'Green Smoothie Bowl',
                'description' => 'Energizing breakfast bowl packed with nutrients',
                'tags' => ['smoothie', 'healthy', 'breakfast', 'vegan'],
                'thumbnail' => 'https://images.unsplash.com/photo-1590301157890-4810ed352733?w=400',
            ],
            [
                'title' => 'Quinoa Salad',
                'description' => 'Protein-rich salad perfect for meal prep',
                'tags' => ['quinoa', 'salad', 'healthy', 'mealprep'],
                'thumbnail' => 'https://images.unsplash.com/photo-1505576399279-565b52d4ac71?w=400',
            ],

            // BBQ & Grilling
            [
                'title' => 'Perfect Smoked Brisket',
                'description' => '12-hour low and slow BBQ brisket with bark',
                'tags' => ['bbq', 'brisket', 'smoked', 'beef'],
                'thumbnail' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=400',
            ],
            [
                'title' => 'Grilled Ribeye Steak',
                'description' => 'Restaurant-quality steak at home with butter basting',
                'tags' => ['steak', 'grilled', 'beef', 'dinner'],
                'thumbnail' => 'https://images.unsplash.com/photo-1600891964092-4316c288032e?w=400',
            ],
            [
                'title' => 'BBQ Ribs Recipe',
                'description' => 'Fall-off-the-bone ribs with homemade BBQ sauce',
                'tags' => ['ribs', 'bbq', 'pork', 'grilling'],
                'thumbnail' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400',
            ],

            // Vegan
            [
                'title' => 'Vegan Burger Patty',
                'description' => 'Juicy plant-based burger that even meat lovers enjoy',
                'tags' => ['vegan', 'burger', 'plantbased', 'healthy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=400',
            ],
            [
                'title' => 'Cashew Cream Pasta',
                'description' => 'Creamy vegan pasta sauce from cashews',
                'tags' => ['vegan', 'pasta', 'cashew', 'creamy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=400',
            ],
            [
                'title' => 'Tofu Stir Fry',
                'description' => 'Crispy tofu with vegetables in savory sauce',
                'tags' => ['tofu', 'stirfry', 'vegan', 'asian'],
                'thumbnail' => 'https://images.unsplash.com/photo-1546069901-eacef0df6022?w=400',
            ],

            // Asian Fusion
            [
                'title' => 'Korean Fried Chicken',
                'description' => 'Ultra crispy twice-fried chicken with gochujang sauce',
                'tags' => ['korean', 'chicken', 'fried', 'spicy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=400',
            ],
            [
                'title' => 'Poke Bowl',
                'description' => 'Hawaiian-Japanese fusion with fresh tuna',
                'tags' => ['poke', 'hawaiian', 'seafood', 'healthy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1546069901-d5bfd2cbfb1f?w=400',
            ],
            [
                'title' => 'Dumplings from Scratch',
                'description' => 'Handmade dumplings with juicy pork filling',
                'tags' => ['dumplings', 'chinese', 'homemade', 'dimsum'],
                'thumbnail' => 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=400',
            ],

            // Breakfast
            [
                'title' => 'Fluffy Pancakes',
                'description' => 'Tall and fluffy American-style pancakes',
                'tags' => ['pancakes', 'breakfast', 'fluffy', 'easy'],
                'thumbnail' => 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=400',
            ],
            [
                'title' => 'Eggs Benedict',
                'description' => 'Classic brunch with perfect poached eggs',
                'tags' => ['eggs', 'brunch', 'breakfast', 'hollandaise'],
                'thumbnail' => 'https://images.unsplash.com/photo-1608039829572-78524f79c4c7?w=400',
            ],
        ];

        $videos = [];
        foreach ($foodContent as $index => $content) {
            // Assign to appropriate creator based on content
            $creator = $this->getCreatorForContent($content, $creators);

            $video = Video::create([
                'user_id' => $creator->id,
                's3_url' => 'https://covre-videos.s3.amazonaws.com/food-' . ($index + 1) . '.mp4',
                'thumbnail_url' => $content['thumbnail'],
                'menu_data' => [
                    'title' => $content['title'],
                    'description' => $content['description'],
                    'tags' => $content['tags'],
                    'duration' => rand(30, 180), // 30 seconds to 3 minutes
                    'category' => 'food',
                ],
            ]);

            $videos[] = $video;
        }

        return $videos;
    }

    private function getCreatorForContent(array $content, array $creators): User
    {
        $tags = $content['tags'];

        if (in_array('italian', $tags) || in_array('pasta', $tags)) {
            return $creators[0]; // Chef Marco
        } elseif (in_array('japanese', $tags) || in_array('sushi', $tags)) {
            return $creators[1]; // Sushi Master Kenji
        } elseif (in_array('dessert', $tags) || in_array('cake', $tags)) {
            return $creators[2]; // Dessert Queen Sarah
        } elseif (in_array('streetfood', $tags) || in_array('mexican', $tags) || in_array('thai', $tags)) {
            return $creators[3]; // Street Food Hunter
        } elseif (in_array('healthy', $tags) || in_array('salad', $tags)) {
            return $creators[4]; // Healthy Cook Rita
        } elseif (in_array('bbq', $tags) || in_array('grilled', $tags)) {
            return $creators[5]; // BBQ King Mike
        } elseif (in_array('vegan', $tags) || in_array('plantbased', $tags)) {
            return $creators[6]; // Vegan Vibes Lisa
        } else {
            return $creators[7]; // Asian Fusion Alex
        }
    }

    private function addEngagement(array $videos, array $users): void
    {
        foreach ($videos as $video) {
            // Random number of likes (popular videos get more)
            $likeCount = fake()->numberBetween(50, 500);
            $likers = fake()->randomElements($users, min($likeCount, count($users)));

            foreach ($likers as $user) {
                Like::create([
                    'user_id' => $user->id,
                    'video_id' => $video->id,
                ]);
            }

            // Random comments
            $commentCount = fake()->numberBetween(5, 30);
            $comments = [
                'This looks so delicious! 😋',
                'Can\'t wait to try this recipe!',
                'Made this last night, amazing!',
                'What can I substitute for...?',
                'Best recipe ever! Thank you!',
                'This turned out perfect!',
                'Love your cooking style!',
                'Great tips, very helpful!',
                'My family loved this!',
                'Wow, looks professional!',
                '10/10 would make again',
                'Simple and tasty!',
                'Clear instructions, thanks!',
                'Yummy! 🤤',
                'Saved for later!',
            ];

            for ($i = 0; $i < $commentCount; $i++) {
                $randomUser = fake()->randomElement($users);
                Comment::create([
                    'user_id' => $randomUser->id,
                    'video_id' => $video->id,
                    'content' => fake()->randomElement($comments),
                ]);
            }

            // Random views (much more than likes)
            $viewCount = fake()->numberBetween($likeCount * 3, $likeCount * 10);
            $viewers = fake()->randomElements($users, min(count($users), 15));

            foreach ($viewers as $user) {
                // Each user can have multiple views
                $userViews = rand(1, 3);
                for ($j = 0; $j < $userViews; $j++) {
                    View::create([
                        'user_id' => $user->id,
                        'video_id' => $video->id,
                        'ip_address' => fake()->ipv4(),
                        'viewed_at' => fake()->dateTimeBetween('-1 month', 'now'),
                    ]);
                }
            }

            // Random bookmarks (fewer than likes)
            $bookmarkCount = fake()->numberBetween(10, $likeCount / 3);
            $bookmarkers = fake()->randomElements($users, min($bookmarkCount, count($users)));

            foreach ($bookmarkers as $user) {
                Bookmark::create([
                    'user_id' => $user->id,
                    'video_id' => $video->id,
                ]);
            }
        }
    }
}
