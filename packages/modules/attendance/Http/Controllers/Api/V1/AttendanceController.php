<?php

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Http\Requests\AttendanceFilterRequest;
use App\Modules\Attendance\Http\Resources\AttendanceRecordResource;
use App\Modules\Attendance\Services\AttendanceRecords;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceRecords $records,
        private readonly BranchAccess $access,
    ) {}

    /**
     * Attendance records of the last 7 days by default; people without the view permission only see their own.
     */
    public function index(AttendanceFilterRequest $request): AnonymousResourceCollection
    {
        $records = $this->records->visibleTo($request->user(), $request->from(7), $request->to(), $request->integer('user_id') ?: null, $request->query('branch_id'));

        return AttendanceRecordResource::collection($records->paginate(min(max($request->integer('per_page', 20), 1), 100))->withQueryString());
    }

    /**
     * The caller's open record, or null when they are not checked in.
     */
    public function current(Request $request): JsonResponse
    {
        $record = $this->attendance->current($request->user());

        return response()->json(['data' => $record ? new AttendanceRecordResource($record) : null]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $record = $this->attendance->checkIn($request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new AttendanceRecordResource($record))->response()->setStatusCode(201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        try {
            $record = $this->attendance->checkOut($request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new AttendanceRecordResource($record))->response();
    }

    /**
     * Hours, lateness and absences per employee for the period (the last 30 days by default).
     */
    public function report(AttendanceFilterRequest $request): JsonResponse
    {
        $actor = $request->user();

        return response()->json([
            'data' => $this->attendance->report($actor->tenant_id, $request->from(), $request->to(), $request->integer('user_id') ?: null, $this->access->effective($actor, $request->query('branch_id'))),
            'period' => ['from' => $request->from()->toDateString(), 'to' => $request->to()->toDateString()],
        ]);
    }
}
