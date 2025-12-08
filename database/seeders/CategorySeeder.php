<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table("categories")->insert([
            ["id" => 1, "name" => "Bass", "slug" => "bass", "icon" => "Activity", "color_class" => "text-violet-400", "bg_class" => "bg-violet-500/10"],
            ["id" => 2, "name" => "Drums", "slug" => "drums", "icon" => "Disc", "color_class" => "text-blue-400", "bg_class" => "bg-blue-500/10"],
            ["id" => 3, "name" => "FX", "slug" => "fx", "icon" => "Zap", "color_class" => "text-emerald-400", "bg_class" => "bg-emerald-500/10"],
            ["id" => 4, "name" => "Synth", "slug" => "synth", "icon" => "Sliders", "color_class" => "text-orange-400", "bg_class" => "bg-orange-500/10"],
            ["id" => 5, "name" => "Vocal", "slug" => "vocal", "icon" => "Mic2", "color_class" => "text-pink-400", "bg_class" => "bg-pink-500/10"],
            ["id" => 6, "name" => "Instrument", "slug" => "instrument", "icon" => "Music2", "color_class" => "text-indigo-400", "bg_class" => "bg-indigo-500/10"],
        ]);
    }
}
