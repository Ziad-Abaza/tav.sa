<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'test-email',
                'name' => 'Test Email',
                'subject' => 'This is a test email from {company_name}',
                'layout_id' => '1',
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['user-group', 'other-group']),
                'content' => '<p>Hello {first_name} {last_name},</p><p>This is a test email from <strong>{company_name}</strong> to confirm your email configuration is working correctly.</p><p>Thank you, </p><p>{company_name} Team</p>',
            ],
            [
                'slug' => 'tenant-welcome-mail',
                'name' => 'Tenant Welcome Email',
                'subject' => 'Welcome to {company_name}, {tenant_company_name}!',
                'layout_id' => '1',
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['tenant-group', 'other-group']),
                'content' => '<p>Hello {tenant_company_name},</p><p>Welcome to <strong>{company_name}</strong>! We are thrilled to have you on board. Your account has been successfully created and is ready to use.</p><p>If you have any questions or need assistance, feel free to reach out.</p><p>Best regards,</p><p>{company_name} Team</p>',
            ],
            [
                'slug' => 'staff-welcome-mail',
                'name' => 'Staff Welcome Email',
                'subject' => 'Welcome to {company_name}, {first_name} {last_name}!',
                'layout_id' => '1',
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['user-group', 'other-group']),
                'content' => '<p>Hello {first_name} {last_name},</p>
                <p>Welcome to <strong>{company_name}</strong>! Your staff account has been successfully created and is ready to use.</p>
                <p>If you have any questions or need assistance, feel free to reach out.</p>
                <p>Best regards,</p>
                <p>{company_name} Team</p>',
            ],
            [
                'slug' => 'new-tenant-reminder-email-to-admin',
                'name' => 'New Tenant Reminder Email to Admin',
                'subject' => 'New tenant {tenant_company_name} has signed up',
                'layout_id' => '1',
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['tenant-group', 'other-group', 'user-group']),
                'content' => '<p>Hi {first_name} {last_name},</p><p>A new tenant <strong>{tenant_company_name}</strong> has just signed up on <strong>{company_name}.</strong></p><p>Please log in to your admin panel to review the details.</p><p>Regards,</p><p>{company_name} </p>',
            ],
            [
                'slug' => 'email-confirmation',
                'name' => 'Email Confirmation',
                'subject' => 'Verify Your Email Address',
                'layout_id' => '1',
                'type' => null,
                'merge_fields_groups' => json_encode(['user-group', 'other-group']),
                'content' => '<p>Hello {first_name} {last_name},</p>
                            <p>Thank you for signing up! Please verify your email address by clicking the button below:</p>
                            <p><a href="{verification_url}" class="button">Verify Email Address</a></p>
                            <p>If you did not create an account, no further action is required.</p>
                            <p>Thanks,<br>{company_name} Team</p>',
            ],
            [
                'slug' => 'password-reset',
                'name' => 'Password Reset',
                'subject' => 'Reset Password Notification',
                'layout_id' => '1',
                'type' => null,
                'merge_fields_groups' => json_encode(['user-group', 'other-group']),
                'content' => '<p>Hello {first_name} {last_name},</p>
                            <p>You are receiving this email because we received a password reset request for your account.</p>
                            <p><a href="{reset_url}" class="button">Reset Password</a></p>
                            <p>This password reset link will expire in 60 minutes.</p>
                            <p>If you did not request a password reset, no further action is required.</p>
                            <p>Regards,<br>{company_name} Team</p>',
            ],
            [
                'slug' => 'ticket-created',
                'name' => 'New Ticket Created (Admin Notification)',
                'subject' => 'New Support Ticket Created - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello Admin,</p>
                    <p>A new support ticket has been created and requires your attention.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Details</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Priority:</td><td style="padding: 8px 0;">{ticket_priority}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Department:</td><td style="padding: 8px 0;">{ticket_department}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Status:</td><td style="padding: 8px 0;">{ticket_status}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Created:</td><td style="padding: 8px 0;">{ticket_created_at}</td></tr>
                        </table>
                    </div>

                    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #1976d2;">Customer Information</h3>
                        <p><strong>Company:</strong> {tenant_company_name}</p>
                    </div>

                    <div style="background-color: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #f57c00;">Ticket Description</h3>
                        <div style="background-color: #ffffff; padding: 15px; border-left: 4px solid #ff9800; border-radius: 4px;">
                            {ticket_body}
                        </div>
                    </div>

                    <p>Please review and assign this ticket at your earliest convenience.</p>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{admin_url}" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Ticket in Admin Panel</a>
                    </div>

                    <p>Best regards,<br>{company_name} System</p>',
            ],
            // Ticket Reply Notification (To Tenant)
            [
                'slug' => 'ticket-reply-tenant',
                'name' => 'Ticket Reply Notification (To Tenant)',
                'subject' => 'Support Ticket Reply - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello {tenant_company_name},</p>
                    <p>You have received a new reply on your support ticket.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Status:</td><td style="padding: 8px 0;">{ticket_status}</td></tr>
                        </table>
                    </div>

                    <p>You can view the full conversation and reply by clicking the link below:</p>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{ticket_url}" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View & Reply to Ticket</a>
                    </div>

                    <p>Best regards,<br>{company_name} Support Team</p>',
            ],

            // Ticket Reply Notification (To Admin)
            [
                'slug' => 'ticket-reply-admin',
                'name' => 'Ticket Reply Notification (To Admin)',
                'subject' => 'New Reply on Ticket {ticket_id} - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello {assigned_user_name},</p>
                    <p>A new reply has been added to ticket <strong>{ticket_id}</strong>.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Details</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Priority:</td><td style="padding: 8px 0;">{ticket_priority}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Status:</td><td style="padding: 8px 0;">{ticket_status}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Customer:</td><td style="padding: 8px 0;">{tenant_company_name}</td></tr>
                        </table>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{ticket_admin_url}" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Ticket in Admin Panel</a>
                    </div>

                    <p>Best regards,<br>{company_name} System</p>',
            ],

            [
                'slug' => 'ticket-status-changed',
                'name' => 'Ticket Status Changed Notification',
                'subject' => 'Ticket Status Updated - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello {tenant_company_name},</p>
                    <p>The status of your support ticket has been updated.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Previous Status:</td><td style="padding: 8px 0;">{previous_status}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">New Status:</td><td style="padding: 8px 0; color: #007bff;"><strong>{new_status}</strong></td></tr>
                        </table>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{ticket_url}" style="background-color: #17a2b8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Ticket Details</a>
                    </div>

                    <p>Best regards,<br>{company_name} Support Team</p>',
            ],

            // Admin Ticket Status Change Notification
            [
                'slug' => 'ticket-status-changed-admin',
                'name' => 'Ticket Status Changed Notification (Admin)',
                'subject' => 'Ticket Status Updated by Tenant - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello Admin,</p>
                    <p>A ticket status has been updated by the tenant.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Customer:</td><td style="padding: 8px 0;">{tenant_company_name}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Previous Status:</td><td style="padding: 8px 0;">{previous_status}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">New Status:</td><td style="padding: 8px 0; color: #007bff;"><strong>{new_status}</strong></td></tr>
                        </table>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{ticket_admin_url}" style="background-color: #17a2b8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View in Admin Panel</a>
                    </div>

                    <p>Best regards,<br>{company_name} System</p>',
            ],

            // Ticket Assigned
            [
                'slug' => 'ticket-assigned',
                'name' => 'Ticket Assigned Notification',
                'subject' => 'Ticket Assigned to You - {ticket_subject}',
                'layout_id' => 1,
                'type' => 'admin',
                'merge_fields_groups' => json_encode(['ticket-group', 'other-group', 'tenant-group']),
                'content' => '<p>Hello {assigned_user_name},</p>
                    <p>A support ticket has been assigned to you.</p>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #495057;">Ticket Details</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; font-weight: bold;">Ticket ID:</td><td style="padding: 8px 0;">{ticket_id}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Subject:</td><td style="padding: 8px 0;">{ticket_subject}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Priority:</td><td style="padding: 8px 0;">{ticket_priority}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Department:</td><td style="padding: 8px 0;">{ticket_department}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Customer:</td><td style="padding: 8px 0;">{tenant_company_name}</td></tr>
                            <tr><td style="padding: 8px 0; font-weight: bold;">Created:</td><td style="padding: 8px 0;">{ticket_created_at}</td></tr>
                        </table>
                    </div>

                    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #1976d2;">Ticket Description</h3>
                        <div style="background-color: #ffffff; padding: 15px; border-left: 4px solid #2196f3; border-radius: 4px;">
                            {ticket_body}
                        </div>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{ticket_admin_url}" style="background-color: #6f42c1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View & Respond to Ticket</a>
                    </div>

                    <p>Best regards,<br>{company_name} System</p>',
            ],
        ];

        foreach ($templates as $template) {
            if (! EmailTemplate::where('slug', $template['slug'])->exists()) {
                EmailTemplate::create($template);
            }
        }
    }
}
