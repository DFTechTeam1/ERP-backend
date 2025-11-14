<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\NotificationSetting;

class NotificationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // reset notification
        NotificationSetting::truncate();

        $notification_settings = [
            [
                'id' => 1,
                'template' => 'Hi <parameter1>, event interactive baru <b><parameter2></b> sudah disetujui dan bisa mulai dikerjakan.<bubble>Silahkan login untuk melihat detailnya 🙂',
                'template_html' => '<p>Hi &lt;parameter1&gt;, event interactive baru <strong>&lt;parameter2&gt;</strong> sudah disetujui dan bisa mulai dikerjakan.</p><p>Silahkan login untuk melihat detailnya 🙂</p>',
                'action' => 'interactive_event_has_been_approved',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 2,
                'template' => 'Hi <parameter1>, project <b><parameter2></b> sudah disetujui oleh klien! 🎉<bubble>Silahkan mulai kerjakan project ini dan koordinasikan dengan tim.',
                'template_html' => '<p>Hi &lt;parameter1&gt;, project <strong>&lt;parameter2&gt;</strong> sudah disetujui oleh klien! 🎉</p><p>Silahkan mulai kerjakan project ini dan koordinasikan dengan tim.</p>',
                'action' => 'project_deal_has_been_approved',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 3,
                'template' => 'Hi <parameter1>, deadline baru ditambahkan untuk tugas <b><parameter2></b> di project <b><parameter3></b>.<bubble>Deadline: <b><parameter4></b> ⏰<bubble>Pastikan selesai tepat waktu ya!',
                'template_html' => '<p>Hi &lt;parameter1&gt;, deadline baru ditambahkan untuk tugas <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong>.</p><p>Deadline: <strong>&lt;parameter4&gt;</strong> ⏰</p><p>Pastikan selesai tepat waktu ya!</p>',
                'action' => 'deadline_has_been_added',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 4,
                'template' => 'Hi <parameter1>, kamu ditugaskan ke task <b><parameter2></b> di project <b><parameter3></b> oleh <parameter4>.<bubble>Silahkan cek detail task dan mulai kerjakan 💪',
                'template_html' => '<p>Hi &lt;parameter1&gt;, kamu ditugaskan ke task <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong> oleh &lt;parameter4&gt;.</p><p>Silahkan cek detail task dan mulai kerjakan 💪</p>',
                'action' => 'user_has_been_assigned_to_task',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 5,
                'template' => 'Hi <parameter1>, kamu dihapus dari task <b><parameter2></b> di project <b><parameter3></b>.<bubble>Tugas ini akan dikerjakan oleh member lain. Info lebih lanjut hubungi PIC project.',
                'template_html' => '<p>Hi &lt;parameter1&gt;, kamu dihapus dari task <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong>.</p><p>Tugas ini akan dikerjakan oleh member lain. Info lebih lanjut hubungi PIC project.</p>',
                'action' => 'user_has_been_removed_from_task',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 6,
                'template' => 'Hi <parameter1>, tugas <b><parameter2></b> di project <b><parameter3></b> sudah selesai dan bisa kamu cek.<bubble>Task ini dikerjakan oleh <b><parameter4></b> ✅<bubble><image1>',
                'template_html' => '<p>Hi &lt;parameter1&gt;, tugas <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong> sudah selesai dan bisa kamu cek.</p><p>Task ini dikerjakan oleh <strong>&lt;parameter4&gt;</strong> ✅</p><p>&lt;image1&gt;</p>',
                'action' => 'user_submit_their_task_with_image',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 7,
                'template' => 'Hi <parameter1>, kamu ditunjuk sebagai PIC untuk event <b><parameter2></b> 🎯<bubble>Silahkan koordinasikan tim dan pastikan project berjalan lancar.',
                'template_html' => '<p>Hi &lt;parameter1&gt;, kamu ditunjuk sebagai PIC untuk event <strong>&lt;parameter2&gt;</strong> 🎯</p><p>Silahkan koordinasikan tim dan pastikan project berjalan lancar.</p>',
                'action' => 'pic_has_been_assigned_to_event',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 8,
                'template' => 'Hi <parameter1>, tugas <b><parameter2></b> di project <b><parameter3></b> perlu direvisi oleh <parameter4>.<bubble>Catatan revisi: <b><parameter5></b><bubble>Silahkan perbaiki dan submit ulang 🔄',
                'template_html' => '<p>Hi &lt;parameter1&gt;, tugas <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong> perlu direvisi oleh &lt;parameter4&gt;.</p><p>Catatan revisi: <strong>&lt;parameter5&gt;</strong></p><p>Silahkan perbaiki dan submit ulang 🔄</p>',
                'action' => 'task_has_been_revise_by_pic',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 9,
                'template' => 'Hi <parameter1>, tugas <b><parameter2></b> di project <b><parameter3></b> telah di-hold oleh <parameter4>.<bubble>Alasan: <b><parameter5></b><bubble>Task akan dilanjutkan setelah issue teratasi ⏸️',
                'template_html' => '<p>Hi &lt;parameter1&gt;, tugas <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong> telah di-hold oleh &lt;parameter4&gt;.</p><p>Alasan: <strong>&lt;parameter5&gt;</strong></p><p>Task akan dilanjutkan setelah issue teratasi ⏸️</p>',
                'action' => 'task_has_been_hold_by_user',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ],
            [
                'id' => 10,
                'template' => 'Hi <parameter1>, ingat untuk menugaskan tim Marcomm untuk event-event yang akan datang.<bubble>Berikut adalah daftar event yang perlu penugasan Marcomm:<bubble><parameter2>',
                'template_html' => '<p>Hi &lt;parameter1&gt;, ingat untuk menugaskan tim Marcomm untuk event-event yang akan datang.</p><p>Berikut adalah daftar event yang perlu penugasan Marcomm:</p><p>&lt;parameter2&gt;</p>',
                'action' => 'remind_assignment_marcomm',
                'created_at' => '2025-10-30 15:32:31',
                'updated_at' => null
            ]
        ];
    }
}
