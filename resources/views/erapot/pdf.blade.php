@php
    $labels = ['A' => 'Sangat Baik', 'B' => 'Baik', 'C' => 'Cukup', 'D' => 'Kurang', 'E' => 'Sangat Kurang'];
    $skills = [
        'Building' => $r->skill_building,
        'Imagination' => $r->skill_imagination,
        'Creativity' => $r->skill_creativity,
        'Logic Thinking' => $r->skill_logic,
    ];
    $behaviour = [
        'Attends on Time' => $r->behavior_punctual,
        "Don't Leave Class Early" => $r->behavior_stay,
        'Communication' => $r->behavior_communication,
        'Responsibility' => $r->behavior_responsibility,
    ];
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
        }

        body {
            color: #191c1e;
            font-size: 12px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #004ac6;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header h1 {
            color: #004ac6;
            margin: 0;
            font-size: 22px;
        }

        .meta td {
            padding: 3px 6px;
        }

        table.grade {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table.grade th,
        table.grade td {
            border: 1px solid #c3c6d7;
            padding: 7px 10px;
            text-align: left;
        }

        table.grade th {
            background: #dbe1ff;
            color: #00174b;
        }

        .section-title {
            background: #004ac6;
            color: #fff;
            padding: 6px 10px;
            font-weight: bold;
            margin-top: 14px;
            border-radius: 4px;
        }

        .comments {
            border: 1px solid #c3c6d7;
            padding: 10px;
            border-radius: 4px;
            min-height: 50px;
        }

        .sign {
            margin-top: 30px;
            text-align: right;
        }

        .sign img {
            max-height: 70px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>E-RAPOT ROBOTIKU</h1>
        <div>Laporan Perkembangan Siswa</div>
    </div>

    <table class="meta">
        <tr>
            <td><b>Nama</b></td>
            <td>: {{ $r->student->name }}</td>
            <td><b>Kode</b></td>
            <td>: {{ $r->student->student_code }}</td>
        </tr>
        <tr>
            <td><b>Kelas</b></td>
            <td>: {{ $r->kelas->name ?? '-' }}</td>
            <td><b>Semester</b></td>
            <td>: {{ $r->semester }} / {{ $r->year }}</td>
        </tr>
        <tr>
            <td><b>Trainer</b></td>
            <td>: {{ $r->trainer->name ?? '-' }}</td>
            <td><b>Kelas Asal</b></td>
            <td>: {{ $r->student->school_grade ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Penilaian Skill</div>
    <table class="grade">
        <tr>
            <th>Aspek</th>
            <th style="width:60px">Nilai</th>
            <th>Keterangan</th>
        </tr>
        @foreach ($skills as $name => $g)
            <tr>
                <td>{{ $name }}</td>
                <td><b>{{ $g }}</b></td>
                <td>{{ $labels[$g] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="section-title">Penilaian Behaviour</div>
    <table class="grade">
        <tr>
            <th>Aspek</th>
            <th style="width:60px">Nilai</th>
            <th>Keterangan</th>
        </tr>
        @foreach ($behaviour as $name => $g)
            <tr>
                <td>{{ $name }}</td>
                <td><b>{{ $g }}</b></td>
                <td>{{ $labels[$g] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="section-title">Catatan Trainer</div>
    <div class="comments">{{ $r->comments ?: '-' }}</div>

    <div class="sign">
        <div>Trainer,</div>
        @if ($r->signature_image && file_exists(public_path('storage/' . $r->signature_image)))
            <img src="{{ public_path('storage/' . $r->signature_image) }}" alt="ttd">
        @else
            <div style="height:60px"></div>
        @endif
        <div><b>{{ $r->trainer->name ?? '' }}</b></div>
    </div>
</body>

</html>
