<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: DejaVu Sans, sans-serif
        }

        body {
            font-size: 11px
        }

        h1 {
            color: #0476d9;
            font-size: 18px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 5px 7px;
            text-align: left
        }

        th {
            background: #eef4fb
        }
    </style>
</head>

<body>
    <h1>Data Siswa Robotiku</h1>
    <p>Total: {{ $students->count() }} siswa · {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Tipe</th>
                <th>Status</th>
                <th>Sekolah/Asal</th>
                <th>Orang Tua</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $s)
                <tr>
                    <td>{{ $s->student_code }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->registration_type }}</td>
                    <td>{{ $s->status }}</td>
                    <td>{{ $s->school?->name ?? ($s->school_origin ?? '-') }}</td>
                    <td>{{ $s->parent?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
