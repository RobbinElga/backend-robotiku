<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $parents = DB::table('parents')
            ->leftJoin('students', 'students.parent_id', '=', 'parents.id')
            ->select('parents.id', 'parents.name', 'parents.phone', 'parents.greeting', DB::raw('COUNT(students.id) as children_count'))
            ->when($request->filled('search'), fn($x) => $x->where(fn($w) =>
            $w->where('parents.name', 'like', '%' . $request->search . '%')
                ->orWhere('parents.phone', 'like', '%' . $request->search . '%')))
            ->groupBy('parents.id', 'parents.name', 'parents.phone', 'parents.greeting')
            ->orderBy('parents.name')
            ->paginate($request->integer('per_page', 15));

        $ids = collect($parents->items())->pluck('id');
        $children = DB::table('students')->whereIn('parent_id', $ids)
            ->select('parent_id', 'id', 'name', 'student_code', 'status', 'is_verified')
            ->orderBy('name')->get()->groupBy('parent_id');

        $parents->getCollection()->transform(function ($p) use ($children) {
            $p->children = ($children[$p->id] ?? collect())->values();
            return $p;
        });

        return $this->success($parents, 'Data orang tua.');
    }
}
