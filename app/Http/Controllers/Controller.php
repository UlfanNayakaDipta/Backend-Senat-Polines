<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Senat Akademik POLINES - REST API Documentation",
    description: "Dokumentasi API Portal Resmi Senat Akademik Politeknik Negeri Semarang (Polines). Menyediakan endpoint presensi QR real-time, kuorum sidang, aspirasi digital, AI chatbot, manajemen kegiatan, rapat, dan tata kelola akademik.",
    contact: new OA\Contact(
        name: "Sekretariat Senat Akademik POLINES",
        email: "senat@polines.ac.id",
        url: "https://www.polines.ac.id"
    ),
    license: new OA\License(
        name: "MIT License",
        url: "https://opensource.org/licenses/MIT"
    )
)]
#[OA\Server(
    url: "http://127.0.0.1:3000/api",
    description: "Backend API Server (Port 3000)"
)]
#[OA\Server(
    url: "http://127.0.0.1:8000/api",
    description: "Alternative Backend Server (Port 8000)"
)]
#[OA\Tag(name: "Presensi Rapat", description: "Sistem Presensi QR Code & Monitoring Kuorum Sidang Real-time")]
#[OA\Tag(name: "Rapat & Sidang", description: "Manajemen Agenda Rapat dan Sidang Senat")]
#[OA\Tag(name: "AI Chatbot", description: "Layanan Tanya-Jawab Aspirasi & Regulasi Senat Berbasis AI")]
#[OA\Tag(name: "Aspirasi", description: "Pengelolaan Saluran Aspirasi Sivitas Akademika")]
#[OA\Tag(name: "Voting", description: "Pemungutan Suara & E-Voting Sidang Senat")]
#[OA\Tag(name: "Kegiatan", description: "Agenda dan Kegiatan Senat Polines")]
#[OA\Tag(name: "Struktur Organisasi & Komisi", description: "Komisi dan Struktur Kepengurusan Senat")]
#[OA\Tag(name: "Peraturan & Dokumen", description: "Produk Hukum dan Peraturan Senat Akademik")]
#[OA\Tag(name: "Berita", description: "Publikasi Berita dan Warta Senat")]
#[OA\Tag(name: "User", description: "Manajemen Pengguna dan Sivitas Akademika")]
abstract class Controller
{
    //
}
