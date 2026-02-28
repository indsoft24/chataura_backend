<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use Illuminate\Database\Seeder;

class FaqItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['question' => 'How do I change my profile picture?', 'answer' => 'Go to Profile > Edit Profile and tap your avatar to upload a new image.', 'sort_order' => 1],
            ['question' => 'How do I block a user?', 'answer' => 'Open the user\'s profile and tap the menu, then select Block.', 'sort_order' => 2],
            ['question' => 'How do I create a group chat?', 'answer' => 'Go to Contacts > Create Group, add a name and select members.', 'sort_order' => 3],
        ];
        foreach ($items as $i => $item) {
            FaqItem::firstOrCreate(
                ['question' => $item['question']],
                ['answer' => $item['answer'], 'sort_order' => $item['sort_order'] ?? $i + 1]
            );
        }
    }
}
