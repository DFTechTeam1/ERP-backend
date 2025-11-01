<?php

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Example Controller showing NotificationService usage
 * 
 * This controller demonstrates real-world examples of using
 * the NotificationService across different scenarios.
 */
class NotificationExampleController extends Controller
{
    /**
     * Example 1: Simple database notification
     */
    public function example1()
    {
        $user = User::find(1);
        
        $result = NotificationService::send(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Design Homepage',
                'parameter3' => 'Website Redesign Project',
                'parameter4' => 'Project Manager',
            ],
            ['database']
        );
        
        return response()->json([
            'message' => 'Notification sent',
            'result' => $result
        ]);
    }
    
    /**
     * Example 2: Multi-channel notification
     */
    public function example2()
    {
        $user = User::find(1);
        
        NotificationService::sendAsync(
            $user,
            'deadline_has_been_added',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Backend API Development',
                'parameter3' => 'Mobile App Project',
                'parameter4' => '31 October 2025, 17:00',
            ],
            ['email', 'database', 'telegram'],
            [
                'subject' => 'New Deadline Added',
                'action_url' => route('tasks.show', 123),
                'action_text' => 'View Task Details',
                'url' => route('tasks.show', 123)
            ]
        );
        
        return response()->json([
            'message' => 'Notification queued for sending'
        ]);
    }
    
    /**
     * Example 3: Bulk notification to multiple users
     */
    public function example3()
    {
        $users = User::whereIn('id', [1, 2, 3, 4, 5])->get();
        
        NotificationService::sendAsync(
            $users,
            'project_deal_has_been_approved',
            [
                'parameter1' => '{user_name}', // Will be replaced per user
                'parameter2' => 'E-Commerce Platform Development',
            ],
            ['email', 'database']
        );
        
        return response()->json([
            'message' => 'Bulk notifications queued',
            'recipients_count' => $users->count()
        ]);
    }
    
    /**
     * Example 4: Task revision with Slack attachment
     */
    public function example4()
    {
        $user = User::find(1);
        
        NotificationService::sendAsync(
            $user,
            'task_has_been_revise_by_pic',
            [
                'parameter1' => $user->name,
                'parameter2' => 'UI Design Mockup',
                'parameter3' => 'Mobile App Project',
                'parameter4' => 'Lead Designer',
                'parameter5' => 'Please adjust the color scheme to match our brand guidelines. The current blue is too dark.',
            ],
            ['email', 'database', 'slack'],
            [
                'subject' => 'Task Revision Required: UI Design Mockup',
                'action_url' => route('tasks.show', 123),
                'action_text' => 'View Revision Notes',
                'attachment' => [
                    'title' => 'Revision Details',
                    'fields' => [
                        'Task' => 'UI Design Mockup',
                        'Project' => 'Mobile App Project',
                        'Revised By' => 'Lead Designer',
                        'Priority' => 'High'
                    ],
                    'color' => '#dc3545' // Red for revision
                ]
            ]
        );
        
        return response()->json([
            'message' => 'Revision notification sent'
        ]);
    }
    
    /**
     * Example 5: Task completed with proof image
     */
    public function example5()
    {
        $pic = User::find(2); // Project PIC
        $worker = User::find(1);
        
        NotificationService::sendAsync(
            $pic,
            'user_submit_their_task_with_image',
            [
                'parameter1' => $pic->name,
                'parameter2' => 'Logo Design',
                'parameter3' => 'Branding Project',
                'parameter4' => $worker->name,
                'images' => [
                    'image1' => 'https://example.com/storage/proofs/logo-design.png'
                ]
            ],
            ['email', 'database', 'slack'],
            [
                'subject' => 'Task Completed: Logo Design',
                'action_url' => route('tasks.review', 123),
                'action_text' => 'Review Task',
                'url' => route('tasks.review', 123)
            ]
        );
        
        return response()->json([
            'message' => 'Task completion notification sent to PIC'
        ]);
    }
    
    /**
     * Example 6: Telegram notification with inline keyboard
     */
    public function example6()
    {
        $user = User::find(1);
        
        NotificationService::sendAsync(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Database Schema Design',
                'parameter3' => 'Backend Development',
                'parameter4' => 'Tech Lead',
            ],
            ['telegram', 'database'],
            [
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 View Task', 'url' => 'https://app.example.com/tasks/123'],
                            ['text' => '✅ Accept', 'callback_data' => 'accept_task_123']
                        ],
                        [
                            ['text' => '❌ Decline', 'callback_data' => 'decline_task_123']
                        ]
                    ]
                ],
                'url' => route('tasks.show', 123)
            ]
        );
        
        return response()->json([
            'message' => 'Telegram notification with buttons sent'
        ]);
    }
    
    /**
     * Example 7: Check channel availability before sending
     */
    public function example7()
    {
        $user = User::find(1);
        
        // Check what channels are available for this user
        $emailAvailable = NotificationService::isChannelAvailable($user, 'email');
        $telegramAvailable = NotificationService::isChannelAvailable($user, 'telegram');
        $slackAvailable = NotificationService::isChannelAvailable($user, 'slack');
        
        // Validate requested channels
        $requestedChannels = ['email', 'telegram', 'slack'];
        $validChannels = NotificationService::validateChannels($user, $requestedChannels);
        
        // Send only to valid channels
        if (!empty($validChannels)) {
            NotificationService::sendAsync(
                $user,
                'user_has_been_assigned_to_task',
                [
                    'parameter1' => $user->name,
                    'parameter2' => 'Task Name',
                    'parameter3' => 'Project Name',
                    'parameter4' => 'Manager',
                ],
                $validChannels
            );
        }
        
        return response()->json([
            'availability' => [
                'email' => $emailAvailable,
                'telegram' => $telegramAvailable,
                'slack' => $slackAvailable,
            ],
            'valid_channels' => $validChannels,
            'message' => 'Notification sent to available channels'
        ]);
    }
    
    /**
     * Example 8: Using user preferences
     */
    public function example8()
    {
        $user = User::find(1);
        
        // Get user's preferred channels for specific action
        $preferredChannels = NotificationService::getUserChannels($user, 'task_assigned');
        
        // Send using user preferences
        NotificationService::sendAsync(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Task Name',
                'parameter3' => 'Project Name',
                'parameter4' => 'Manager',
            ],
            $preferredChannels // Use user's preferred channels
        );
        
        return response()->json([
            'user_preferences' => $preferredChannels,
            'message' => 'Notification sent according to user preferences'
        ]);
    }
    
    /**
     * Example 9: Real-world task assignment flow
     */
    public function assignTaskWithNotification(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'required|exists:users,id',
        ]);
        
        // Get models (example - adjust to your actual models)
        $task = \App\Models\Task::find($validated['task_id']);
        $user = User::find($validated['user_id']);
        $assigner = auth()->user();
        
        // Business logic: Assign task
        $task->assignee_id = $user->id;
        $task->assigned_by = $assigner->id;
        $task->assigned_at = now();
        $task->save();
        
        // Send notification
        NotificationService::sendAsync(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => $task->name,
                'parameter3' => $task->project->name ?? 'N/A',
                'parameter4' => $assigner->name,
            ],
            ['email', 'database', 'telegram'],
            [
                'subject' => 'New Task Assigned: ' . $task->name,
                'action_url' => route('tasks.show', $task->id),
                'action_text' => 'View Task Details',
                'url' => route('tasks.show', $task->id),
                'title' => 'New Task Assignment',
            ]
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Task assigned and notification sent',
            'task' => $task,
        ]);
    }
    
    /**
     * Example 10: Error handling
     */
    public function example10()
    {
        $user = User::find(1);
        
        try {
            $results = NotificationService::send(
                $user,
                'user_has_been_assigned_to_task',
                [
                    'parameter1' => $user->name,
                    'parameter2' => 'Task Name',
                    'parameter3' => 'Project Name',
                    'parameter4' => 'Manager',
                ],
                ['email', 'database', 'telegram', 'slack']
            );
            
            // Check individual channel results
            foreach ($results as $result) {
                foreach ($result['channels'] as $channel => $channelResult) {
                    if (!$channelResult['success']) {
                        \Log::warning("Failed to send {$channel} notification", [
                            'user' => $user->id,
                            'error' => $channelResult['error'] ?? 'Unknown error'
                        ]);
                    }
                }
            }
            
            return response()->json([
                'message' => 'Notification sending completed',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Notification service error', [
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Failed to send notification',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
