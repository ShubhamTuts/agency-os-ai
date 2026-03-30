<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AOSAI_TABLE_PROJECTS', 'aosai_projects' );
define( 'AOSAI_TABLE_PROJECT_USERS', 'aosai_project_users' );
define( 'AOSAI_TABLE_TASK_LISTS', 'aosai_task_lists' );
define( 'AOSAI_TABLE_TASKS', 'aosai_tasks' );
define( 'AOSAI_TABLE_TASK_USERS', 'aosai_task_users' );
define( 'AOSAI_TABLE_TASK_META', 'aosai_task_meta' );
define( 'AOSAI_TABLE_MILESTONES', 'aosai_milestones' );
define( 'AOSAI_TABLE_MESSAGES', 'aosai_messages' );
define( 'AOSAI_TABLE_COMMENTS', 'aosai_comments' );
define( 'AOSAI_TABLE_FILES', 'aosai_files' );
define( 'AOSAI_TABLE_ACTIVITIES', 'aosai_activities' );
define( 'AOSAI_TABLE_NOTIFICATIONS', 'aosai_notifications' );
define( 'AOSAI_TABLE_AI_LOGS', 'aosai_ai_logs' );

define( 'AOSAI_STATUS_PROJECT_ACTIVE', 'active' );
define( 'AOSAI_STATUS_PROJECT_ARCHIVED', 'archived' );
define( 'AOSAI_STATUS_PROJECT_COMPLETED', 'completed' );
define( 'AOSAI_STATUS_PROJECT_ON_HOLD', 'on_hold' );

define( 'AOSAI_STATUS_TASK_OPEN', 'open' );
define( 'AOSAI_STATUS_TASK_IN_PROGRESS', 'in_progress' );
define( 'AOSAI_STATUS_TASK_DONE', 'done' );
define( 'AOSAI_STATUS_TASK_OVERDUE', 'overdue' );
define( 'AOSAI_STATUS_TASK_CANCELLED', 'cancelled' );

define( 'AOSAI_PRIORITY_LOW', 'low' );
define( 'AOSAI_PRIORITY_MEDIUM', 'medium' );
define( 'AOSAI_PRIORITY_HIGH', 'high' );
define( 'AOSAI_PRIORITY_URGENT', 'urgent' );

define( 'AOSAI_STATUS_MILESTONE_UPCOMING', 'upcoming' );
define( 'AOSAI_STATUS_MILESTONE_IN_PROGRESS', 'in_progress' );
define( 'AOSAI_STATUS_MILESTONE_COMPLETED', 'completed' );
define( 'AOSAI_STATUS_MILESTONE_OVERDUE', 'overdue' );

define( 'AOSAI_PROJECT_ROLE_MANAGER', 'manager' );
define( 'AOSAI_PROJECT_ROLE_MEMBER', 'member' );
define( 'AOSAI_PROJECT_ROLE_VIEWER', 'viewer' );
define( 'AOSAI_PROJECT_ROLE_CLIENT', 'client' );

define( 'AOSAI_NOTIF_TASK_ASSIGNED', 'task_assigned' );
define( 'AOSAI_NOTIF_TASK_COMPLETED', 'task_completed' );
define( 'AOSAI_NOTIF_TASK_DUE', 'task_due' );
define( 'AOSAI_NOTIF_COMMENT_ADDED', 'comment_added' );
define( 'AOSAI_NOTIF_MILESTONE_COMPLETE', 'milestone_complete' );
define( 'AOSAI_NOTIF_MESSAGE_POSTED', 'message_posted' );
define( 'AOSAI_NOTIF_FILE_UPLOADED', 'file_uploaded' );
define( 'AOSAI_NOTIF_MENTION', 'mention' );
