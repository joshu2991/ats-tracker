<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    /**
     * Store user feedback.
     */
    public function store(StoreFeedbackRequest $request)
    {
        try {
            $feedback = Feedback::create([
                'rating' => $request->rating,
                'description' => $request->description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send email notification via SES
            $this->sendFeedbackEmail($feedback);

            // Return success - stay on current GET route (results page)
            return back()->with('success', 'Thank you for your feedback!');
        } catch (\Exception $e) {
            Log::error('Failed to store feedback', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            return back()->withErrors([
                'feedback' => 'Failed to submit feedback. Please try again later.',
            ]);
        }
    }

    /**
     * Send feedback email notification via SES.
     */
    protected function sendFeedbackEmail(Feedback $feedback): void
    {
        try {
            $toEmail = config('mail.from.address');
            $toName = config('mail.from.name');

            Mail::raw(
                $this->buildFeedbackEmailContent($feedback),
                function ($message) use ($toEmail, $toName, $feedback) {
                    $message->to($toEmail, $toName)
                        ->subject('New Resume Checker Feedback - '.$feedback->rating.' Stars');
                }
            );
        } catch (\Exception $e) {
            // Log error but don't fail the request if email fails
            Log::warning('Failed to send feedback email', [
                'error' => $e->getMessage(),
                'feedback_id' => $feedback->id,
            ]);
        }
    }

    /**
     * Build email content for feedback notification.
     */
    protected function buildFeedbackEmailContent(Feedback $feedback): string
    {
        $stars = str_repeat('⭐', $feedback->rating).str_repeat('☆', 5 - $feedback->rating);
        $content = "New Feedback Received\n\n";
        $content .= "Rating: {$stars} ({$feedback->rating}/5)\n\n";

        if ($feedback->description) {
            $content .= "Description:\n{$feedback->description}\n\n";
        }

        $content .= "Submitted: {$feedback->created_at->format('Y-m-d H:i:s')}\n";
        $content .= "IP Address: {$feedback->ip_address}\n";
        $content .= "User Agent: {$feedback->user_agent}\n";

        return $content;
    }
}
