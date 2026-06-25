<?php

namespace Database\Seeders;

use App\Models\LandingContent;
use Illuminate\Database\Seeder;

class LandingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'navbar' => [
                'links' => [
                    ['label' => 'Tentang', 'href' => '#tentang'],
                    ['label' => 'Program', 'href' => '#program'],
                    ['label' => 'Galeri', 'href' => '#galeri'],
                    ['label' => 'Kontak', 'href' => '#kontak'],
                ],
                'cta_label' => 'Daftar',
            ],
            'hero' => [
                'badge' => '#1 Kelas Robotik Anak',
                'title' => 'Belajar Robotik Jadi Seru!',
                'subtitle' => 'Bantu anak Anda mengembangkan kreativitas dan kemampuan problem solving lewat kelas robotika yang interaktif dan menyenangkan.',
                'primary_cta' => ['label' => 'Daftar Sekarang', 'href' => '/daftar'],
                'secondary_cta' => ['label' => 'Lihat Program', 'href' => '#program'],
                'image_url' => '/images/hero-anak.jpg',
                'stat' => ['value' => '500+', 'label' => 'Siswa Telah Bergabung'],
            ],
            'mitra' => ['caption' => 'Dipercaya oleh Mitra Sekolah'],
            'about' => [
                'badge' => 'Mengenal RobotiKU',
                'title' => 'Bermain Sambil Mengasah Logika Masa Depan',
                'paragraphs' => [
                    'Di RobotiKU, kami percaya anak belajar paling efektif saat sedang bersenang-senang.',
                    'Dengan metode hands-on learning, anak membangun kreasi mereka sendiri dari nol.',
                ],
                'highlights' => [
                    ['icon' => 'puzzle', 'title' => 'Hands-on', 'desc' => 'Praktek langsung'],
                    ['icon' => 'users', 'title' => 'Kolaboratif', 'desc' => 'Kerja sama tim'],
                ],
            ],
            'programs' => [
                'title' => 'Program Pilihan Untuk Setiap Usia',
                'subtitle' => 'Kurikulum berjenjang sesuai tahap perkembangan kognitif anak.',
                'items' => [
                    ['id' => 'robo-kids', 'icon' => 'bot', 'age' => 'Usia 5-7 Tahun', 'title' => 'Robo Kids', 'desc' => 'Pengenalan dasar robotika tanpa layar.', 'points' => ['Motorik halus', 'Pengenalan pola'], 'featured' => false],
                    ['id' => 'iot-junior', 'icon' => 'cpu', 'age' => 'Usia 8-10 Tahun', 'title' => 'IoT Junior', 'desc' => 'Merakit elektronik & mikrokontroler dasar.', 'points' => ['Rangkaian listrik', 'Block-based coding'], 'featured' => true],
                    ['id' => 'pro-coder', 'icon' => 'code', 'age' => 'Usia 11-15 Tahun', 'title' => 'Pro Coder', 'desc' => 'Python & C++ untuk proyek kompleks.', 'points' => ['Text-based coding', 'Logika algoritma'], 'featured' => false],
                ],
            ],
            'achievements' => [
                'title' => 'Kompetisi dan Penghargaan',
                'desc' => 'Siswa kami rutin meraih prestasi di kompetisi robotik nasional & internasional.',
                'items' => [
                    ['icon' => 'trophy', 'title' => 'Olimpiade Robotik Nasional', 'desc' => 'Juara 1 Maze Solving Junior 2023.'],
                    ['icon' => 'star', 'title' => 'TechKids Expo Innovation', 'desc' => 'Inovasi terbaik proyek Smart Trash Bin.'],
                ],
            ],
            'testimonials' => [
                'title' => 'Apa Kata Mereka',
                'desc' => 'Pengalaman orang tua & siswa yang telah bergabung.',
                'items' => [
                    ['name' => 'Ibu Budi', 'role' => 'Orang tua Robo Kids', 'initials' => 'IB', 'rating' => 5, 'text' => 'Anak saya jadi lebih kreatif. Mentornya sangat sabar!'],
                    ['name' => 'Pak Andi', 'role' => 'Orang tua Pro Coder', 'initials' => 'PA', 'rating' => 5, 'text' => 'Kurikulumnya terstruktur. Sangat recommended!'],
                ],
            ],
            'gallery' => [
                'title' => 'Keseruan di Kelas',
                'desc' => 'Intip keseruan anak merakit robot pertama mereka.',
                'main' => ['image_url' => '/images/galeri-1.jpg', 'caption' => 'Kerja Sama Tim'],
                'tiles' => [
                    ['type' => 'text', 'title' => 'Ide Kreatif', 'desc' => null, 'image_url' => null],
                    ['type' => 'image', 'image_url' => '/images/galeri-2.jpg', 'title' => null, 'desc' => null],
                    ['type' => 'text', 'title' => 'Kompetisi Tahunan', 'desc' => 'Wadah unjuk gigi hasil karya siswa.', 'image_url' => null],
                ],
            ],
            'cta' => [
                'title' => 'Siap Memulai Petualangan?',
                'desc' => 'Pilih jalur yang sesuai untuk Anda atau sekolah Anda.',
                'partner' => ['title' => 'Bergabung Menjadi Mitra', 'desc' => 'Bawa ekstrakurikuler robotik modern ke sekolah Anda.'],
                'trial' => ['title' => 'Daftar Free Trial', 'desc' => 'Rasakan langsung keseruan merakit robot!'],
                'admins' => [
                    ['label' => 'Admin Jabodetabek', 'phone' => '6281234567890'],
                    ['label' => 'Admin Pontianak', 'phone' => '6281234567891'],
                ],
            ],
            'contact' => [
                'tagline' => 'Membangun generasi masa depan melalui pendidikan robotika yang kreatif.',
                'address' => 'Jl. Robotika No. 123, Jakarta Selatan',
                'phone' => '+62 812 3456 7890',
                'email' => 'halo@robotiku.id',
                'whatsapp' => '6281234567890',
                'socials' => [
                    ['type' => 'facebook', 'url' => 'https://facebook.com/robotiku'],
                    ['type' => 'instagram', 'url' => 'https://instagram.com/robotiku'],
                    ['type' => 'youtube', 'url' => 'https://youtube.com/@robotiku'],
                ],
            ],
        ];

        foreach ($defaults as $section => $content) {
            LandingContent::updateOrCreate(['section' => $section], ['content' => $content]);
        }
    }
}
