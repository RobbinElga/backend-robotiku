<?php

namespace App\Support;

class LandingSchema
{
    /** Daftar section yang valid + aturan validasi (relatif terhadap `content`). */
    public static function map(): array
    {
        return [
            'navbar' => [
                'links'          => ['required', 'array'],
                'links.*.label'  => ['required', 'string', 'max:40'],
                'links.*.href'   => ['required', 'string', 'max:200'],
                'cta_label'      => ['nullable', 'string', 'max:40'],
            ],

            'hero' => [
                'badge'                => ['nullable', 'string', 'max:100'],
                'title'                => ['required', 'string', 'max:200'],
                'subtitle'             => ['nullable', 'string', 'max:500'],
                'primary_cta.label'    => ['nullable', 'string', 'max:50'],
                'primary_cta.href'     => ['nullable', 'string', 'max:200'],
                'secondary_cta.label'  => ['nullable', 'string', 'max:50'],
                'secondary_cta.href'   => ['nullable', 'string', 'max:200'],
                'image_url'            => ['nullable', 'string', 'max:300'],
                'stat.value'           => ['nullable', 'string', 'max:50'],
                'stat.label'           => ['nullable', 'string', 'max:100'],
            ],

            'mitra' => [
                'caption' => ['nullable', 'string', 'max:150'],
            ],

            'about' => [
                'badge'             => ['nullable', 'string', 'max:100'],
                'title'             => ['required', 'string', 'max:200'],
                'paragraphs'        => ['required', 'array'],
                'paragraphs.*'      => ['string', 'max:1000'],
                'highlights'        => ['array'],
                'highlights.*.icon' => ['required_with:highlights', 'string', 'max:40'],
                'highlights.*.title' => ['required_with:highlights', 'string', 'max:60'],
                'highlights.*.desc' => ['nullable', 'string', 'max:150'],
            ],

            'programs' => [
                'title'             => ['required', 'string', 'max:200'],
                'subtitle'          => ['nullable', 'string', 'max:300'],
                'items'             => ['required', 'array', 'min:1'],
                'items.*.id'        => ['required', 'string', 'max:50'],
                'items.*.icon'      => ['required', 'string', 'max:40'],
                'items.*.age'       => ['nullable', 'string', 'max:50'],
                'items.*.title'     => ['required', 'string', 'max:80'],
                'items.*.desc'      => ['nullable', 'string', 'max:400'],
                'items.*.points'    => ['array'],
                'items.*.points.*'  => ['string', 'max:80'],
                'items.*.featured'  => ['boolean'],
            ],

            'achievements' => [
                'title'         => ['required', 'string', 'max:200'],
                'desc'          => ['nullable', 'string', 'max:500'],
                'items'         => ['array'],
                'items.*.icon'  => ['required_with:items', 'string', 'max:40'],
                'items.*.title' => ['required_with:items', 'string', 'max:120'],
                'items.*.desc'  => ['nullable', 'string', 'max:400'],
            ],

            'testimonials' => [
                'title'           => ['required', 'string', 'max:200'],
                'desc'            => ['nullable', 'string', 'max:300'],
                'items'           => ['array'],
                'items.*.name'    => ['required_with:items', 'string', 'max:80'],
                'items.*.role'    => ['nullable', 'string', 'max:120'],
                'items.*.initials' => ['nullable', 'string', 'max:4'],
                'items.*.rating'  => ['required_with:items', 'numeric', 'min:0', 'max:5'],
                'items.*.text'    => ['required_with:items', 'string', 'max:500'],
            ],

            'gallery' => [
                'title'                  => ['required', 'string', 'max:200'],
                'desc'                   => ['nullable', 'string', 'max:300'],
                'main.image_url'         => ['nullable', 'string', 'max:300'],
                'main.caption'           => ['nullable', 'string', 'max:120'],
                'tiles'                  => ['array'],
                'tiles.*.type'           => ['required_with:tiles', 'in:image,text'],
                'tiles.*.image_url'      => ['nullable', 'string', 'max:300'],
                'tiles.*.title'          => ['nullable', 'string', 'max:120'],
                'tiles.*.desc'           => ['nullable', 'string', 'max:200'],
            ],

            'cta' => [
                'title'            => ['required', 'string', 'max:200'],
                'desc'             => ['nullable', 'string', 'max:300'],
                'partner.title'    => ['nullable', 'string', 'max:120'],
                'partner.desc'     => ['nullable', 'string', 'max:300'],
                'trial.title'      => ['nullable', 'string', 'max:120'],
                'trial.desc'       => ['nullable', 'string', 'max:300'],
                'admins'           => ['array'],
                'admins.*.label'   => ['required_with:admins', 'string', 'max:80'],
                'admins.*.phone'   => ['required_with:admins', 'string', 'max:20'],
            ],

            'contact' => [
                'tagline'        => ['nullable', 'string', 'max:300'],
                'address'        => ['nullable', 'string', 'max:300'],
                'phone'          => ['nullable', 'string', 'max:30'],
                'email'          => ['nullable', 'email', 'max:120'],
                'whatsapp'       => ['nullable', 'string', 'max:20'],
                'socials'        => ['array'],
                'socials.*.type' => ['required_with:socials', 'string', 'max:30'],
                'socials.*.url'  => ['required_with:socials', 'string', 'max:200'],
            ],
        ];
    }

    public static function sections(): array
    {
        return array_keys(self::map());
    }

    public static function rulesFor(string $section): ?array
    {
        return self::map()[$section] ?? null;
    }
}
