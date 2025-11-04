<?php

namespace App\Http\Controllers\Examples;

use App\Models\User;
use App\Services\NotificationService;
use Google\Service\Logging\RecentQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service Usage Examples
 * 
 * This controller demonstrates various ways to use the NotificationService
 * with correct placeholder format from notification_settings table.
 * 
 * Placeholders:
 * - <parameter1>, <parameter2>, ..., <parameterN> for text
 * - <image1>, <image2>, ..., <imageN> for images
 * - <audio1>, <audio2>, ..., <audioN> for audio files
 * - <document1>, <document2>, ..., <documentN> for documents
 * - <bubble> for new line
 */
class NotificationExampleController
{
    /**
     * Example 1: Simple task assignment (database only)
     */
    public function example1_simpleTaskAssignment()
    {
        $user = User::find(1);
        $task = (object)[
            'id' => 123,
            'title' => 'Design Homepage',
            'project' => (object)['name' => 'Website Redesign']
        ];
        
        NotificationService::send(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => $task->title,
                'parameter3' => $task->project->name,
                'parameter4' => Auth::user()->name ?? 'Manager',
            ],
            ['database']
        );
        
        return response()->json(['message' => 'Simple notification sent']);
    }

    /**
     * Example 2: Task with image attachment (email + database)
     */
    public function example2_taskWithImage()
    {
        $user = User::find(1);
        $task = (object)[
            'id' => 456,
            'title' => 'Review UI Design',
            'screenshot_url' => 'https://cdn.example.com/designs/ui-v1.png',
        ];
        
        NotificationService::sendAsync(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => $task->title,
                'parameter3' => 'Mobile App Project',
                'parameter4' => 'Lead Designer',
                
                // Images array
                'images' => [
                    'image1' => $task->screenshot_url,
                ]
            ],
            ['email', 'database'],
            [
                'subject' => 'New Design Review Task',
                'action_url' => route('tasks.show', $task->id),
                'action_text' => 'View Task',
            ]
        );
        
        return response()->json(['message' => 'Notification with image queued']);
    }

    /**
     * Example 3: Deadline reminder with document (all channels)
     */
    public function example3_deadlineWithDocument()
    {
        $user = User::find(1);
        $task = (object)[
            'id' => 789,
            'title' => 'Complete API Documentation',
            'deadline' => '31 October 2025',
            'requirements_file' => 'https://cdn.example.com/docs/api-requirements.pdf',
        ];
        
        NotificationService::sendAsync(
            recipients: $user,
            action: 'deadline_has_been_added',
            data: [
                'parameter1' => $user->name,
                'parameter2' => $task->title,
                'parameter3' => 'Backend Development',
                'parameter4' => $task->deadline,
                
                // Documents array
                'documents' => [
                    'document1' => $task->requirements_file,
                ]
            ],
            channels: ['email', 'database', 'telegram', 'slack'],
            options: [
                'subject' => 'Deadline Added: ' . $task->title,
                'action_url' => route('tasks.show', $task->id),
                'action_text' => 'View Task',
                
                // Slack attachment
                'attachment' => [
                    'title' => 'Task Details',
                    'fields' => [
                        'Deadline' => $task->deadline,
                        'Priority' => 'High',
                    ],
                    'color' => '#dc3545'
                ],
                
                // Telegram buttons
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '✅ View Task', 'url' => route('tasks.show', $task->id)],
                        ['text' => '📄 Download Doc', 'url' => $task->requirements_file]
                    ]]
                ]
            ]
        );
        
        return response()->json(['message' => 'Deadline notification with document queued']);
    }

    /**
     * Example 4: Meeting notification with audio recording
     */
    public function example4_meetingWithAudio()
    {
        $user = User::find(1);
        $meeting = (object)[
            'id' => 101,
            'title' => 'Sprint Planning',
            'scheduled_at' => '5 November 2025, 10:00 AM',
            'previous_recording' => 'https://cdn.example.com/recordings/sprint-2024-10.mp3',
        ];
        
        NotificationService::sendAsync(
            $user,
            'meeting_reminder',
            [
                'parameter1' => $user->name,
                'parameter2' => $meeting->title,
                'parameter3' => $meeting->scheduled_at,
                
                // Audio array
                'audios' => [
                    'audio1' => $meeting->previous_recording,
                ]
            ],
            ['email', 'database', 'telegram'],
            [
                'subject' => 'Upcoming Meeting: ' . $meeting->title,
                'action_url' => route('meetings.show', $meeting->id),
                'action_text' => 'Join Meeting',
            ]
        );
        
        return response()->json(['message' => 'Meeting notification with audio queued']);
    }

    /**
     * Example 5: Design review with multiple images
     */
    public function example5_designReviewMultipleImages()
    {
        $user = User::find(1);
        $review = (object)[
            'id' => 202,
            'design_title' => 'Landing Page Redesign',
            'preview_url' => 'https://cdn.example.com/designs/landing-preview.png',
            'mockup_url' => 'https://cdn.example.com/designs/landing-mockup.png',
            'reference_url' => 'https://cdn.example.com/designs/landing-reference.jpg',
        ];
        
        NotificationService::sendAsync(
            $user,
            'design_review_requested',
            [
                'parameter1' => $user->name,
                'parameter2' => $review->design_title,
                'parameter3' => 'Marketing Team',
                
                // Multiple images
                'images' => [
                    'image1' => $review->preview_url,
                    'image2' => $review->mockup_url,
                    'image3' => $review->reference_url,
                ]
            ],
            ['email', 'database'],
            [
                'subject' => 'Design Review: ' . $review->design_title,
                'action_url' => route('designs.review', $review->id),
                'action_text' => 'Review Design',
            ]
        );
        
        return response()->json(['message' => 'Design review with multiple images queued']);
    }

    /**
     * Example 6: Project approval with documents and images
     */
    public function example6_projectApprovalComplete()
    {
        $user = User::find(1);
        $project = (object)[
            'id' => 303,
            'name' => 'E-commerce Platform',
            'proposal_pdf' => 'https://cdn.example.com/projects/proposal.pdf',
            'budget_xlsx' => 'https://cdn.example.com/projects/budget.xlsx',
            'mockup_png' => 'https://cdn.example.com/projects/mockup.png',
        ];
        
        NotificationService::sendAsync(
            $user,
            'project_approval_needed',
            [
                'parameter1' => $user->name,
                'parameter2' => $project->name,
                'parameter3' => 'Development Team',
                'parameter4' => 'Manager Name',
                
                // Documents
                'documents' => [
                    'document1' => $project->proposal_pdf,
                    'document2' => $project->budget_xlsx,
                ],
                
                // Images
                'images' => [
                    'image1' => $project->mockup_png,
                ]
            ],
            ['email', 'database', 'slack'],
            [
                'subject' => 'Approval Required: ' . $project->name,
                'action_url' => route('projects.approval', $project->id),
                'action_text' => 'Review & Approve',
                
                'attachment' => [
                    'title' => 'Project Details',
                    'fields' => [
                        'Status' => 'Pending Approval',
                        'Department' => 'Development',
                    ],
                    'color' => '#ffc107'
                ]
            ]
        );
        
        return response()->json(['message' => 'Project approval notification queued']);
    }

    /**
     * Example 7: Bulk notification to multiple users
     */
    public function example7_bulkNotification()
    {
        $users = User::where('department_id', 5)->get();
        $announcement = (object)[
            'title' => 'Company Holiday Notice',
            'date' => '10 November 2025',
            'document' => 'https://cdn.example.com/announcements/holiday-2025.pdf',
        ];
        
        NotificationService::sendAsync(
            $users->toArray(),
            'company_announcement',
            [
                'parameter1' => 'Team',
                'parameter2' => $announcement->title,
                'parameter3' => $announcement->date,
                
                'documents' => [
                    'document1' => $announcement->document,
                ]
            ],
            ['email', 'database'],
            [
                'subject' => $announcement->title,
            ]
        );
        
        return response()->json([
            'message' => 'Bulk notification queued',
            'recipients' => $users->count()
        ]);
    }

    /**
     * Example 8: Conditional channel based on user preference
     */
    public function example8_conditionalChannels()
    {
        $user = User::find(1);
        
        // Get available channels for user
        $preferredChannels = NotificationService::getUserChannels($user, 'task_completed');
        
        // Validate channels are available
        $availableChannels = NotificationService::validateChannels($user, $preferredChannels);
        
        NotificationService::sendAsync(
            $user,
            'task_completed',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Homepage Design',
                'parameter3' => 'Website Project',
            ],
            $availableChannels ?: ['database'], // Fallback to database
            [
                'subject' => 'Task Completed',
            ]
        );
        
        return response()->json([
            'message' => 'Notification sent to available channels',
            'channels' => $availableChannels
        ]);
    }

    /**
     * Example 9: Error handling and logging
     */
    public function example9_withErrorHandling()
    {
        $user = User::find(1);
        
        try {
            $results = NotificationService::send(
                $user,
                'test_notification',
                [
                    'parameter1' => $user->name,
                    'parameter2' => 'Test Message',
                ],
                ['email', 'database', 'telegram'],
                [
                    'subject' => 'Test Notification',
                ]
            );
            
            // Check results
            $successChannels = [];
            $failedChannels = [];
            
            foreach ($results[0]['channels'] as $channel => $result) {
                if ($result['success']) {
                    $successChannels[] = $channel;
                } else {
                    $failedChannels[] = $channel;
                }
            }
            
            return response()->json([
                'message' => 'Notification sent',
                'success_channels' => $successChannels,
                'failed_channels' => $failedChannels,
                'details' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Notification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Example 10: Using storage URLs for files
     */
    public function example10_withStorageFiles()
    {
        $user = User::find(1);
        
        // For Laravel 8+, use url() for public storage
        // For private files, you can generate signed URLs
        $taskFile = url('storage/tasks/123/requirements.pdf');
        $screenshot = url('storage/tasks/123/screenshot.png');
        
        NotificationService::sendAsync(
            $user,
            'task_files_uploaded',
            [
                'parameter1' => $user->name,
                'parameter2' => 'API Development',
                'parameter3' => '2 files',
                
                'images' => [
                    'image1' => $screenshot,
                ],
                
                'documents' => [
                    'document1' => $taskFile,
                ]
            ],
            ['email', 'database', 'telegram'],
            [
                'subject' => 'New Task Files Uploaded',
                'action_url' => route('tasks.show', 123),
                'action_text' => 'View Task',
            ]
        );
        
        return response()->json(['message' => 'Notification with storage files queued']);
    }

    /**
     * Example 11: Template with <bubble> for formatting
     * 
     * Template should be in notification_settings:
     * "Hi <parameter1>,<bubble><bubble>Task: <parameter2><bubble>Project: <parameter3><bubble><bubble>Thanks!"
     */
    public function example11_withBubbleFormatting()
    {
        $user = User::find(1);
        
        // The <bubble> in template will be converted to:
        // - \n in plain text (telegram, slack)
        // - <br> in HTML (email)
        
        NotificationService::sendAsync(
            $user,
            'formatted_task_assignment',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Implement Login Feature',
                'parameter3' => 'Authentication Module',
                'parameter4' => 'Tech Lead',
            ],
            ['email', 'database', 'telegram'],
            [
                'subject' => 'New Task Assignment',
            ]
        );
        
        return response()->json(['message' => 'Formatted notification queued']);
    }

    /**
     * Example 12: Real-world task revision scenario
     */
    public function example12_realWorldRevision()
    {
        $user = User::find(1);
        $task = (object)[
            'id' => 999,
            'title' => 'Dashboard UI Design',
            'project' => (object)['name' => 'Admin Panel'],
            'revision_notes' => 'Please adjust the color scheme and add more whitespace',
            'current_design' => 'https://cdn.example.com/designs/dashboard-v2.png',
            'reference' => 'https://cdn.example.com/designs/reference-dashboard.png',
            'feedback_doc' => 'https://cdn.example.com/feedback/dashboard-feedback.pdf',
        ];
        
        NotificationService::sendAsync(
            $user,
            'task_has_been_revise_by_pic',
            [
                'parameter1' => $user->name,
                'parameter2' => $task->title,
                'parameter3' => $task->project->name,
                'parameter4' => 'Lead Designer',
                'parameter5' => $task->revision_notes,
                
                'images' => [
                    'image1' => $task->current_design,
                    'image2' => $task->reference,
                ],
                
                'documents' => [
                    'document1' => $task->feedback_doc,
                ]
            ],
            ['email', 'database', 'telegram', 'slack'],
            [
                'subject' => 'Revision Required: ' . $task->title,
                'action_url' => route('tasks.show', $task->id),
                'action_text' => 'View Revision Details',
                
                'attachment' => [
                    'title' => 'Revision Details',
                    'fields' => [
                        'Task' => $task->title,
                        'Project' => $task->project->name,
                        'Status' => 'Needs Revision',
                    ],
                    'color' => '#dc3545'
                ],
                
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '🔍 View Task', 'url' => route('tasks.show', $task->id)],
                        ['text' => '📄 Feedback', 'url' => $task->feedback_doc],
                    ]]
                ]
            ]
        );
        
        return response()->json(['message' => 'Revision notification sent to all channels']);
    }
}
