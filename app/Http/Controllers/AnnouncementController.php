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
            'content' => 'required|string'
        ]);

        $announcement = Announcements::create([
            'content' => $request->content
        ]);

        return response()->json([
            'message' => 'Announcement created successfully',
            'data' => $announcement
        ], 201);
    }

    // ✅ Get all announcements
    public function getannouncements()
    {
        $announcements = Announcements::latest()->get();

        return response()->json($announcements, 200);
    }
}
