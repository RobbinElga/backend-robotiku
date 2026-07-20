@php
    $grades = ['A', 'B', 'C', 'D', 'E'];
    $skills = [
        'Building' => $r->skill_building,
        'Imagination' => $r->skill_imagination,
        'Creativity' => $r->skill_creativity,
        'Logic Thinking' => $r->skill_logic,
    ];
    $behav = [
        'Attends on time' => $r->behavior_punctual,
        "Don't leave class early" => $r->behavior_stay,
        'Communication' => $r->behavior_communication,
        'Responsibility' => $r->behavior_responsibility,
    ];
    $school = optional($r->student->school)->name ?? ($r->student->school_origin ?? '-');
    $ay = $r->year . '/' . ($r->year + 1);
    $date = $r->report_date ? \Carbon\Carbon::parse($r->report_date)->translatedFormat('j F Y') : '';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        body {
            margin: 0;
        }

        .t-center {
            text-align: center;
        }

        .b {
            font-weight: bold;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .grade-tbl td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
        }

        .no-border td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }

        .band {
            background: #e5e5e5;
            text-align: center;
            font-weight: bold;
        }

        .grade {
            width: 34px;
            text-align: center;
        }

        .comments {
            vertical-align: top;
            text-align: justify;
            width: 35%;
        }

        .sign-line {
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 2px;
            min-width: 170px;
        }
    </style>
</head>

<body>
    {{-- Header + logo pojok kiri atas --}}
    <table class="no-border">
        <tr>
            <td style="width:90px; vertical-align:middle;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height:58px">
                @endif
            </td>
            <td class="t-center" style="vertical-align:middle;">
                <div class="b" style="font-size:16px">ROBOTIKU INDONESIA</div>
                <div class="b" style="font-size:12px">TAHUN AJARAN {{ $ay }} ROBOTIKU CLUB REPORT CARD
                </div>
            </td>
            <td style="width:90px"></td>
        </tr>
    </table>

    {{-- Identitas --}}
    <table class="no-border" style="margin-top:6px">
        <tr>
            <td style="width:120px" class="b">SCHOOL</td>
            <td>: {{ $school }}</td>
            <td style="width:70px" class="b">CLASS</td>
            <td>: {{ $r->student->school_grade ?? '-' }}</td>
        </tr>
        <tr>
            <td class="b">STUDENT NAME</td>
            <td>: {{ $r->student->name }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="b">GROUP</td>
            <td>: {{ $r->kelas->name ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    {{-- Rating key --}}
    <table class="grade-tbl" style="margin-top:8px">
        <tr>
            <td class="band" colspan="5">REPORT RATING KEY</td>
        </tr>
        <tr>
            <td>A : Very Good</td>
            <td>B : Good</td>
            <td>C : Average</td>
            <td>D : Poor</td>
            <td>E : Very Poor</td>
        </tr>
    </table>

    {{-- Topics --}}
    <table class="grade-tbl" style="margin-top:8px">
        <tr>
            <td class="band" colspan="2">Topics and Activities</td>
        </tr>
        @forelse($r->topics ?? [] as $t)
            <tr>
                <td style="width:35%" class="b">{{ $t['topic'] ?? '' }}</td>
                <td>{{ $t['activity'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td style="width:35%">&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        @endforelse
    </table>

    {{-- Skills / Behaviour + Comments --}}
    <table class="grade-tbl" style="margin-top:8px">
        <tr>
            <td class="band" style="width:45%">Skills</td>
            <td class="band" colspan="5">Grade</td>
            <td rowspan="12" class="comments">
                <div class="b t-center" style="margin-bottom:6px">Comments</div>
                {{ $r->comments }}
            </td>
        </tr>
        <tr>
            <td></td>
            @foreach ($grades as $g)
                <td class="grade b">{{ $g }}</td>
            @endforeach
        </tr>
        @foreach ($skills as $name => $val)
            <tr>
                <td class="t-center">{{ $name }}</td>
                @foreach ($grades as $g)
                    <td class="grade">{{ $val === $g ? '√' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr>
            <td class="band">Behaviour</td>
            <td class="band" colspan="5">Grade</td>
        </tr>
        <tr>
            <td></td>
            @foreach ($grades as $g)
                <td class="grade b">{{ $g }}</td>
            @endforeach
        </tr>
        @foreach ($behav as $name => $val)
            <tr>
                <td class="t-center">{{ $name }}</td>
                @foreach ($grades as $g)
                    <td class="grade">{{ $val === $g ? '√' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    {{-- Tanda tangan trainer --}}
    <table class="no-border" style="margin-top:24px">
        <tr>
            <td style="width:60%"></td>
            <td class="t-center">
                RobotiKU<br>
                {{ $r->report_place ?? 'Pontianak' }}{{ $date ? ', ' . $date : '' }}
                @if ($sig)
                    <div><img src="{{ $sig }}" style="height:60px; margin-top:6px"></div>
                @else
                    <div style="height:66px"></div>
                @endif
                <div class="sign-line">{{ optional($r->trainer)->name ?? 'Trainer' }}</div>
            </td>
        </tr>
    </table>
</body>

</html>
