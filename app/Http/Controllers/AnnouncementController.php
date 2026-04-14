<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcements;

class AnnouncementController extends Controller
{
    // ✅ Create announcement
    public function createAnnouncement(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        $announcement = Announcements::create([
            'content' => $request->content,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json([
            'message' => 'Announcement created successfully',
            'data' => $announcement
        ], 201);
    }


    public function updateAnnouncement(Request $request, $id)
    {
        $request->validate([
            'content' => 'sometimes|string',
            'is_active' => 'nullable|boolean'
        ]);

        $announcement = Announcements::find($id);

        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found'], 404);
        }

        $announcement->update([
            'content' => $request->content,
            'is_active' => $request->is_active ?? $announcement->is_active
        ]);

        return response()->json([
            'message' => 'Announcement updated successfully',
            'data' => $announcement
        ], 200);
    }

    // Get all announcements
    public function getannouncements()
    {
        $announcements = Announcements::latest()->where('is_active', 1)->get();

        return response()->json($announcements, 200);
    }

    public function getannouncementsAdmin()
    {
        $announcements = Announcements::latest()->where('is_archived', 0)->get();

        return response()->json($announcements, 200);
    }
}
