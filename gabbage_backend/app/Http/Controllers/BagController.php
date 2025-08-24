<?php

namespace App\Http\Controllers;

use App\Models\Bag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BagController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->id;
        $bags = Bag::where('organization_id', $organizationId)
            ->with(['createdBy:id,name', 'bagIssues'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => ['bags' => $bags]
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_bags' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        $bag = Bag::create([
            'organization_id' => $request->user()->id,
            'number_of_bags' => $request->number_of_bags,
            'description' => $request->description,
            'created_by' => $request->user()->id
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Bag created successfully',
            'data' => ['bag' => $bag->load('createdBy:id,name')]
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $bag = Bag::where('id', $id)
            ->where('organization_id', $organizationId)
            ->with(['createdBy:id,name', 'bagIssues.driver:id,name'])
            ->first();

        if (!$bag) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Bag not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => ['bag' => $bag]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $bag = Bag::where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$bag) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Bag not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'number_of_bags' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        $updateData = [];
        if ($request->has('number_of_bags')) $updateData['number_of_bags'] = $request->number_of_bags;
        if ($request->has('description')) $updateData['description'] = $request->description;

        $bag->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Bag updated successfully',
            'data' => ['bag' => $bag->fresh()->load('createdBy:id,name')]
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $bag = Bag::where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$bag) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Bag not found'
            ], 404);
        }

        $bag->delete();

        return response()->json([
            'status' => true,
            'message' => 'Bag deleted successfully'
        ], 200);
    }
}