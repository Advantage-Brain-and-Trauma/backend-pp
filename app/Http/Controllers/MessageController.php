<?php
namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $messages = Message::whereNull('parent_id')
            ->when($search, function($q) use ($search) {
                $q->where(function($q2) use ($search) {
                    $q2->where('sender_name', 'like', "%{$search}%")
                       ->orWhere('subject', 'like', "%{$search}%")
                       ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(30);
        // AJAX live-search: return only the list items
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $itemsHtml = view('messages.partials.list-items', compact('messages'))->render();
            return response()->json([
                'items' => $itemsHtml,
                'total' => $messages->total(),
            ]);
        }

        return view('messages.index', compact('messages', 'search'));
    }

    public function create()
    {
        return view('messages.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'  => 'required|string|max:255',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:50',
        ]);

        Message::create([
            'sender_name' => Auth::user()->name,
            'sender_type' => 'admin',
            'subject'     => $validated['subject'],
            'body'        => $validated['body'],
            'category'    => $validated['category'] ?? null,
            'status'      => 'sent',
            'is_read'     => true,
        ]);

        return redirect()->route('messages.index')->with('success', 'Message sent successfully.');
    }

    public function show(Message $message)
    {
        if (!$message->is_read && $message->sender_type === 'patient') {
            $message->update(['is_read' => true]);
        }
        $allMessages = Message::whereNull('parent_id')->latest()->take(30)->get();
        $message->load('replies');
        return view('messages.show', compact('message', 'allMessages'));
    }

    public function reply(Request $request, Message $message)
    {
        $request->validate(['body' => 'required|string']);

        Message::create([
            'parent_id'   => $message->id,
            'sender_name' => Auth::user()->name,
            'sender_type' => 'admin',
            'subject'     => 'Re: ' . $message->subject,
            'body'        => $request->body,
            'status'      => 'sent',
            'is_read'     => true,
        ]);

        return redirect()->route('messages.show', $message)->with('success', 'Reply sent.');
    }
}
