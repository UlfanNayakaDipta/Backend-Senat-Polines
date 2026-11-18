<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HeroSettings;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeroSettingsTest extends TestCase
{
    use RefreshDatabase;

    public const DEFAULTS = [
        'eyebrow' => 'Organ Tertinggi Normatif Akademik',
        'title' => "Senat Akademik\nPoliteknik Negeri Semarang",
        'description' => 'Perumus kebijakan akademik, penjaga mutu pendidikan vokasi, dan penyalur aspirasi civitas akademika — memastikan tata kelola Polines berjalan transparan, akuntabel, dan berintegritas.',
        'background_image' => '/images/gedung-polines.jpg',
        'primary_label' => 'Profil & Komisi',
        'secondary_label' => 'Repositori Produk Hukum',
    ];

    public function test_default_migration_and_missing_row_read_without_write(): void
    {
        $this->assertDatabaseCount('hero_settings', 1);
        $this->getJson('/api/hero-settings')->assertOk()->assertExactJson(self::DEFAULTS);
        DB::table('hero_settings')->delete();
        $this->getJson('/api/hero-settings')->assertOk()->assertExactJson(self::DEFAULTS);
        $this->assertDatabaseCount('hero_settings', 0);
    }

    public function test_additive_migration_preserves_legacy_data_and_timestamps(): void
    {
        $migration = require database_path('migrations/2026_11_18_000000_add_hero_content_fields_to_hero_settings_table.php');
        $migration->down();
        $legacy = [
            'title' => "Judul Lama\nBaris Kedua",
            'description' => 'Deskripsi tersimpan',
            'primary_label' => 'Tombol Lama',
            'secondary_label' => 'Arsip Lama',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-02-01 00:00:00',
        ];
        DB::table('hero_settings')->where('id', 1)->update($legacy);
        $migration->up();
        $this->assertDatabaseCount('hero_settings', 1);
        $this->assertDatabaseHas('hero_settings', ['id' => 1] + $legacy + [
            'eyebrow' => self::DEFAULTS['eyebrow'],
            'background_image' => self::DEFAULTS['background_image'],
        ]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('hero_settings', 'eyebrow'));
        $this->assertFalse(Schema::hasColumn('hero_settings', 'background_image'));
        $this->assertDatabaseHas('hero_settings', ['id' => 1] + $legacy);
        $migration->up();
        $this->getJson('/api/hero-settings')->assertOk()->assertExactJson(array_replace(self::DEFAULTS, array_diff_key($legacy, array_flip(['created_at', 'updated_at']))));
    }

    private function editable(array $changes = []): array
    {
        return array_replace(array_diff_key(self::DEFAULTS, array_flip(['primary_label', 'secondary_label'])), $changes);
    }

    public function test_admin_upsert_normalizes_without_trimming_and_persists(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        DB::table('hero_settings')->delete();
        $data = $this->editable(['title' => " A\r\n<script>B</script> ", 'description' => " A\rB "]);
        $expected = array_replace(self::DEFAULTS, $data, ['title' => " A\n<script>B</script> ", 'description' => " A\nB "]);
        $this->putJson('/api/hero-settings', $data)->assertOk()->assertExactJson($expected);
        $this->getJson('/api/hero-settings')->assertExactJson($expected);
        $this->putJson('/api/hero-settings', $this->editable())->assertOk();
        $this->assertDatabaseCount('hero_settings', 1);
    }

    public function test_admin_updates_only_screenshot_content_and_retains_stored_labels(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $labels = ['primary_label' => 'Profil Tersimpan', 'secondary_label' => 'Arsip Tersimpan'];
        DB::table('hero_settings')->where('id', 1)->update($labels + ['created_at' => '2026-01-01 00:00:00']);
        $data = $this->editable(['eyebrow' => 'Pengantar', 'title' => "Judul\nKedua", 'description' => 'Deskripsi baru', 'background_image' => 'https://example.com/hero.jpg']);
        $expected = array_replace(self::DEFAULTS, $data, $labels);
        $this->putJson('/api/hero-settings', $data)->assertOk()->assertExactJson($expected);
        $this->getJson('/api/hero-settings')->assertOk()->assertExactJson($expected);
        $this->assertDatabaseHas('hero_settings', ['id' => 1, 'created_at' => '2026-01-01 00:00:00'] + $expected);
        foreach (['primary_label', 'secondary_label'] as $field) {
            $before = DB::table('hero_settings')->first();
            $this->putJson('/api/hero-settings', $data + [$field => 'Changed'])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertEquals($before, DB::table('hero_settings')->first());
        }
    }

    public function test_unauthorized_requests_do_not_write(): void
    {
        $this->putJson('/api/hero-settings', $this->editable())->assertUnauthorized();
        $this->withToken('invalid')->putJson('/api/hero-settings', $this->editable())->assertUnauthorized();
        foreach (['anggota', 'ketua_senat', 'sekretaris'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->putJson('/api/hero-settings', $this->editable())->assertForbidden();
        }
        $this->getJson('/api/hero-settings')->assertExactJson(self::DEFAULTS);
    }

    public function test_validation_boundaries_unicode_unknown_fields_and_no_partial_write(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['eyebrow' => 160, 'title' => 160, 'description' => 500] as $field => $limit) {
            $this->putJson('/api/hero-settings', $this->editable([$field => str_repeat('界', $limit)]))->assertOk();
            foreach (['', '   ', "\u{2003}", 1, null, [], str_repeat('界', $limit + 1)] as $value) {
                $before = DB::table('hero_settings')->first();
                $this->putJson('/api/hero-settings', $this->editable([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
                $this->assertEquals($before, DB::table('hero_settings')->first());
            }
        }
        foreach (["a\nb\nc", "a\n ", "\na"] as $title) {
            $this->putJson('/api/hero-settings', $this->editable(['title' => $title]))->assertUnprocessable()->assertJsonValidationErrors('title');
        }
        $this->putJson('/api/hero-settings', $this->editable(['eyebrow' => "a\rb"]))->assertUnprocessable()->assertJsonValidationErrors('eyebrow');
        $this->putJson('/api/hero-settings', $this->editable() + ['extra' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('extra');
        foreach (array_keys($this->editable()) as $field) {
            $before = DB::table('hero_settings')->first();
            $this->putJson('/api/hero-settings', array_diff_key($this->editable(), [$field => true]))->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertEquals($before, DB::table('hero_settings')->first());
        }
    }

    public function test_background_image_accepts_safe_local_paths_and_http_urls(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['/images/hero.jpg', '/images/hero/banner_2.webp', 'http://example.com/hero', 'https://example.com/hero.jpg?v=2&size=large', 'https://example.com/photo%20one.jpg', 'HTTPS://example.com/hero.jpg', '/images/'.str_repeat('a', 2040)] as $image) {
            $this->putJson('/api/hero-settings', $this->editable(['background_image' => $image]))->assertOk()->assertJsonPath('background_image', $image);
        }
    }

    public function test_background_image_rejects_malformed_or_unsafe_urls_without_writing(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([null, 1, [], '', ' ', '/images/', '/other/hero.jpg', 'images/hero.jpg', '//example.com/hero.jpg', '/images/../secret.jpg', '/images/./hero.jpg', '/images/a/../../secret.jpg', '/images/%2e%2e/hero.jpg', '/images/%252e%252e/hero.jpg', '/images/a%2f..%2fsecret', '/images/hero.jpg%00', '/images/a\\hero.jpg', '/images/hero image.jpg', "https://example.com/hero\n.jpg", 'https://example.com/hero%0a.jpg', 'https://example.com/hero%zz.jpg', 'https://example.com/hero\\.jpg', 'https://example.com/hero.jpg"', 'https://example.com/hero.jpg)', 'https://user:secret@example.com/hero.jpg', 'javascript:alert(1)', 'data:image/png;base64,AA', 'ftp://example.com/hero.jpg', 'http://', 'https://bad_host/hero.jpg', 'https:///hero.jpg', 'https://example.com:99999/hero.jpg', '/images/'.str_repeat('a', 2041)] as $image) {
            $before = DB::table('hero_settings')->first();
            $this->putJson('/api/hero-settings', $this->editable(['background_image' => $image]))->assertUnprocessable()->assertJsonValidationErrors('background_image');
            $this->assertEquals($before, DB::table('hero_settings')->first());
            try {
                HeroSettings::validate(array_replace(self::DEFAULTS, ['background_image' => $image]));
                $this->fail('Invalid image accepted by shared validator');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('background_image', $error->errors());
            }
        }
    }

    public function test_shared_validator_accepts_full_payload_and_validates_readonly_labels(): void
    {
        $this->assertSame(self::DEFAULTS, HeroSettings::DEFAULTS);
        $this->assertSame(self::DEFAULTS, HeroSettings::validate(self::DEFAULTS));
        $this->assertSame($this->editable(), HeroSettings::validateUpdate($this->editable()));
        $this->assertSame(" A\nB ", HeroSettings::validate(array_replace(self::DEFAULTS, ['title' => " A\r\nB "]))['title']);
        foreach (['primary_label', 'secondary_label'] as $field) {
            $this->assertSame(str_repeat('界', 80), HeroSettings::validate(array_replace(self::DEFAULTS, [$field => str_repeat('界', 80)]))[$field]);
            foreach (['', ' ', 1, null, [], "a\rb", str_repeat('界', 81), "\xFF"] as $value) {
                try {
                    HeroSettings::validate(array_replace(self::DEFAULTS, [$field => $value]));
                    $this->fail('Invalid label accepted');
                } catch (ValidationException $error) {
                    $this->assertArrayHasKey($field, $error->errors());
                }
            }
        }
        foreach (array_keys(self::DEFAULTS) as $field) {
            try {
                HeroSettings::validate(array_diff_key(self::DEFAULTS, [$field => true]));
                $this->fail('Missing field accepted');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($field, $error->errors());
            }
        }
        try {
            HeroSettings::validate(self::DEFAULTS + ['unknown' => 'x']);
            $this->fail('Unknown field accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('unknown', $error->errors());
        }
    }
}
