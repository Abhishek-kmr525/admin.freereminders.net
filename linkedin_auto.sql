-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 24, 2025 at 10:31 AM
-- Server version: 10.6.22-MariaDB-cll-lve
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `linkedin_auto`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`qcidb7kof599`@`localhost` PROCEDURE `CleanupOldData` ()   BEGIN
    -- Delete old activity logs (older than 6 months)
    DELETE FROM customer_activity 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    -- Delete old webhook logs (older than 3 months)
    DELETE FROM webhook_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    
    -- Delete expired notifications
    DELETE FROM system_notifications 
    WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    -- Delete old API usage logs (older than 1 year)
    DELETE FROM api_usage 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_encrypted`, `created_at`, `updated_at`) VALUES
(1, 'gemini_api_key', '', 'string', 'Google Gemini API Key', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(2, 'openai_api_key', '', 'string', 'OpenAI ChatGPT API Key', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(3, 'default_post_time', '09:00', 'string', 'Default posting time', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(4, 'max_posts_per_day', '10', 'integer', 'Maximum posts per customer per day', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(5, 'enable_analytics', 'true', 'boolean', 'Enable post analytics tracking', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(6, 'linkedin_api_version', 'v2', 'string', 'LinkedIn API version to use', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(7, 'system_timezone', 'UTC', 'string', 'System timezone for scheduling', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(8, 'retry_failed_posts', 'true', 'boolean', 'Automatically retry failed posts', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(9, 'max_retry_attempts', '3', 'integer', 'Maximum retry attempts for failed posts', 0, '2025-09-24 17:10:25', '2025-09-24 17:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2025-09-22 18:10:57', '2025-09-22 18:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `api_settings`
--

CREATE TABLE `api_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `gemini_api_key` text DEFAULT NULL,
  `chatgpt_api_key` text DEFAULT NULL,
  `linkedin_client_id` varchar(255) DEFAULT NULL,
  `linkedin_client_secret` varchar(255) DEFAULT NULL,
  `linkedin_access_token` text DEFAULT NULL,
  `razorpay_key_id` varchar(255) DEFAULT NULL,
  `razorpay_key_secret` varchar(255) DEFAULT NULL,
  `stripe_public_key` varchar(255) DEFAULT NULL,
  `stripe_secret_key` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `api_settings`
--

INSERT INTO `api_settings` (`id`, `gemini_api_key`, `chatgpt_api_key`, `linkedin_client_id`, `linkedin_client_secret`, `linkedin_access_token`, `razorpay_key_id`, `razorpay_key_secret`, `stripe_public_key`, `stripe_secret_key`, `updated_at`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-22 18:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `api_usage`
--

CREATE TABLE `api_usage` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `ai_model` varchar(50) NOT NULL,
  `request_type` varchar(50) NOT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(8,4) DEFAULT 0.0000,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `success` tinyint(1) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `automations`
--

CREATE TABLE `automations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `topic` varchar(500) NOT NULL,
  `ai_model` enum('gemini','chatgpt') NOT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 5,
  `post_time` time NOT NULL DEFAULT '09:00:00',
  `content_style` enum('professional','casual','educational','motivational','storytelling') DEFAULT 'professional',
  `instructions` text DEFAULT NULL,
  `status` enum('active','paused','completed','cancelled') DEFAULT 'active',
  `total_posts_generated` int(11) DEFAULT 0,
  `successful_posts` int(11) DEFAULT 0,
  `failed_posts` int(11) DEFAULT 0,
  `last_generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `automation_dashboard`
-- (See below for the actual view)
--
CREATE TABLE `automation_dashboard` (
`id` int(11)
,`customer_id` int(11)
,`name` varchar(255)
,`topic` varchar(500)
,`ai_model` enum('gemini','chatgpt')
,`status` enum('active','paused','completed','cancelled')
,`duration_days` int(11)
,`created_at` timestamp
,`total_scheduled_posts` bigint(21)
,`published_posts` bigint(21)
,`pending_posts` bigint(21)
,`failed_posts` bigint(21)
,`next_post_time` datetime /* mariadb-5.3 */
,`last_published_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `content_templates`
--

CREATE TABLE `content_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `template_content` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `content_style` enum('professional','casual','educational','motivational','storytelling') DEFAULT NULL,
  `ai_model` enum('gemini','chatgpt','both') DEFAULT 'both',
  `is_active` tinyint(1) DEFAULT 1,
  `usage_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `content_templates`
--

INSERT INTO `content_templates` (`id`, `name`, `description`, `template_content`, `category`, `content_style`, `ai_model`, `is_active`, `usage_count`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Professional Insight', 'Professional insights and industry knowledge sharing', 'Create a professional LinkedIn post sharing insights about {topic}. Focus on industry trends, best practices, and valuable knowledge. Include relevant hashtags and end with a question to encourage engagement.', 'Professional', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(2, 'Educational Content', 'Educational and informative posts', 'Create an educational LinkedIn post about {topic}. Break down complex concepts into easy-to-understand points. Use bullet points or numbered lists where appropriate. Include practical tips that readers can implement immediately.', 'Education', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(3, 'Success Story', 'Motivational success stories and lessons learned', 'Create a motivational LinkedIn post about {topic} in the form of a success story or lesson learned. Use storytelling techniques to engage readers and inspire them. End with a key takeaway or call-to-action.', 'Motivation', 'storytelling', 'both', 1, 0, NULL, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(4, 'Industry News Commentary', 'Commentary on recent industry news and trends', 'Create a LinkedIn post commenting on recent developments in {topic}. Provide your unique perspective and analysis. Keep it balanced and professional while encouraging discussion in the comments.', 'News', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(5, 'Tips and Tricks', 'Practical tips and actionable advice', 'Create a LinkedIn post sharing practical tips and tricks related to {topic}. Use a numbered list format (3-5 tips) and make each tip actionable and specific. Include relevant hashtags and encourage sharing.', 'Tips', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:10:25', '2025-09-24 17:10:25'),
(6, 'Professional Insight', 'Professional insights and industry knowledge sharing', 'Create a professional LinkedIn post sharing insights about {topic}. Focus on industry trends, best practices, and valuable knowledge. Include relevant hashtags and end with a question to encourage engagement.', 'Professional', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:10:48', '2025-09-24 17:10:48'),
(7, 'Educational Content', 'Educational and informative posts', 'Create an educational LinkedIn post about {topic}. Break down complex concepts into easy-to-understand points. Use bullet points or numbered lists where appropriate. Include practical tips that readers can implement immediately.', 'Education', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:10:48', '2025-09-24 17:10:48'),
(8, 'Success Story', 'Motivational success stories and lessons learned', 'Create a motivational LinkedIn post about {topic} in the form of a success story or lesson learned. Use storytelling techniques to engage readers and inspire them. End with a key takeaway or call-to-action.', 'Motivation', 'storytelling', 'both', 1, 0, NULL, '2025-09-24 17:10:48', '2025-09-24 17:10:48'),
(9, 'Industry News Commentary', 'Commentary on recent industry news and trends', 'Create a LinkedIn post commenting on recent developments in {topic}. Provide your unique perspective and analysis. Keep it balanced and professional while encouraging discussion in the comments.', 'News', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:10:48', '2025-09-24 17:10:48'),
(10, 'Tips and Tricks', 'Practical tips and actionable advice', 'Create a LinkedIn post sharing practical tips and tricks related to {topic}. Use a numbered list format (3-5 tips) and make each tip actionable and specific. Include relevant hashtags and encourage sharing.', 'Tips', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:10:48', '2025-09-24 17:10:48'),
(11, 'Professional Insight', 'Professional insights and industry knowledge sharing', 'Create a professional LinkedIn post sharing insights about {topic}. Focus on industry trends, best practices, and valuable knowledge. Include relevant hashtags and end with a question to encourage engagement.', 'Professional', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:11:03', '2025-09-24 17:11:03'),
(12, 'Educational Content', 'Educational and informative posts', 'Create an educational LinkedIn post about {topic}. Break down complex concepts into easy-to-understand points. Use bullet points or numbered lists where appropriate. Include practical tips that readers can implement immediately.', 'Education', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:11:03', '2025-09-24 17:11:03'),
(13, 'Success Story', 'Motivational success stories and lessons learned', 'Create a motivational LinkedIn post about {topic} in the form of a success story or lesson learned. Use storytelling techniques to engage readers and inspire them. End with a key takeaway or call-to-action.', 'Motivation', 'storytelling', 'both', 1, 0, NULL, '2025-09-24 17:11:03', '2025-09-24 17:11:03'),
(14, 'Industry News Commentary', 'Commentary on recent industry news and trends', 'Create a LinkedIn post commenting on recent developments in {topic}. Provide your unique perspective and analysis. Keep it balanced and professional while encouraging discussion in the comments.', 'News', 'professional', 'both', 1, 0, NULL, '2025-09-24 17:11:03', '2025-09-24 17:11:03'),
(15, 'Tips and Tricks', 'Practical tips and actionable advice', 'Create a LinkedIn post sharing practical tips and tricks related to {topic}. Use a numbered list format (3-5 tips) and make each tip actionable and specific. Include relevant hashtags and encourage sharing.', 'Tips', 'educational', 'both', 1, 0, NULL, '2025-09-24 17:11:03', '2025-09-24 17:11:03');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `country` varchar(10) NOT NULL DEFAULT 'us',
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `subscription_plan` varchar(50) DEFAULT NULL,
  `subscription_status` enum('trial','active','expired','cancelled') DEFAULT 'trial',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `subscription_ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `oauth_provider` varchar(20) DEFAULT NULL,
  `oauth_provider_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `password`, `country`, `phone`, `status`, `subscription_plan`, `subscription_status`, `trial_ends_at`, `subscription_ends_at`, `created_at`, `updated_at`, `oauth_provider`, `oauth_provider_id`) VALUES
(2, 'abhi', 'mr.abhishek525@gmail.coim', '$2y$10$uZMiHfXx/ONSJqwXrg2/p.7Eie1AT8YNrX31w3iakj4XVSiuC3/Zi', 'in', '9229402206', 'active', NULL, 'trial', '2025-10-06 16:32:21', NULL, '2025-09-22 22:02:21', '2025-09-22 22:02:21', NULL, NULL),
(4, 'Abhishek Kumar', 'mr.abhishek525@gmail.com', NULL, 'us', NULL, 'active', NULL, 'trial', '2025-10-08 18:49:03', NULL, '2025-09-24 11:49:03', '2025-09-24 17:30:55', 'linkedin', 'ZiKQlj0Pc0'),
(5, 'Abhishek Kumar', 'aaabhishek786@gmail.com', NULL, 'us', NULL, 'active', NULL, 'trial', '2025-10-08 18:59:08', NULL, '2025-09-24 11:59:08', '2025-09-24 11:59:08', 'google', '102410143940667969360'),
(6, 'Ravi Raj', 'ravirajarg@gmail.com', NULL, 'in', NULL, 'active', NULL, 'trial', '2025-10-08 23:33:46', NULL, '2025-09-24 16:33:46', '2025-09-24 16:33:46', 'google', '112337160397856525499');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity`
--

CREATE TABLE `customer_activity` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_logs`
--

CREATE TABLE `customer_activity_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_activity_logs`
--

INSERT INTO `customer_activity_logs` (`id`, `customer_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'account_created', 'New account created', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-22 22:02:21'),
(2, 2, 'login', 'User logged in successfully', '223.185.63.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 08:43:08'),
(4, 4, 'oauth_signup', 'New account created via google', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 11:49:03'),
(5, 4, 'logout', 'User logged out', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 11:58:17'),
(6, 4, 'oauth_login', 'Logged in via google', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 11:58:29'),
(7, 4, 'logout', 'User logged out', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 11:58:40'),
(8, 5, 'oauth_signup', 'New account created via google', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 11:59:08'),
(9, 4, 'oauth_login', 'Logged in via google', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 13:54:25'),
(10, 4, 'oauth_login', 'Logged in via google', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 15:43:25'),
(11, 6, 'oauth_signup', 'New account created via google', '49.37.25.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-09-24 16:33:46'),
(12, 4, 'oauth_login', 'Logged in via linkedin', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-24 17:02:13'),
(13, 4, 'oauth_login', 'Logged in via linkedin', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-24 17:02:44'),
(14, 4, 'oauth_login', 'Logged in via linkedin', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-24 17:25:20'),
(15, 4, 'oauth_login', 'Logged in via linkedin', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-24 17:30:44'),
(16, 4, 'oauth_login', 'Logged in via linkedin', '152.58.159.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '2025-09-24 17:30:55');

-- --------------------------------------------------------

--
-- Table structure for table `customer_automations`
--

CREATE TABLE `customer_automations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `topic` text NOT NULL,
  `ai_provider` enum('gemini','chatgpt') DEFAULT 'gemini',
  `post_time` time NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_of_week` varchar(20) NOT NULL,
  `content_template` text DEFAULT NULL,
  `hashtags` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_automations`
--

INSERT INTO `customer_automations` (`id`, `customer_id`, `name`, `topic`, `ai_provider`, `post_time`, `start_date`, `end_date`, `days_of_week`, `content_template`, `hashtags`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 'test', 'test', 'gemini', '09:00:00', '2025-09-24', '2025-10-24', '1,2,3,4,5', 'test', '#test, #yy', 'active', '2025-09-24 17:04:54', '2025-09-24 17:04:54');

-- --------------------------------------------------------

--
-- Table structure for table `customer_generated_posts`
--

CREATE TABLE `customer_generated_posts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `automation_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `status` enum('pending','posted','failed') DEFAULT 'pending',
  `linkedin_post_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_linkedin_tokens`
--

CREATE TABLE `customer_linkedin_tokens` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `linkedin_user_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_post_analytics`
--

CREATE TABLE `customer_post_analytics` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `likes_count` int(11) DEFAULT 0,
  `comments_count` int(11) DEFAULT 0,
  `shares_count` int(11) DEFAULT 0,
  `impressions` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_sessions`
--

CREATE TABLE `customer_sessions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_gateway` varchar(20) NOT NULL,
  `gateway_payment_id` varchar(255) DEFAULT NULL,
  `gateway_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_orders`
--

CREATE TABLE `payment_orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `gateway_order_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `status` enum('created','completed','failed') DEFAULT 'created',
  `gateway_payment_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_analytics`
--

CREATE TABLE `post_analytics` (
  `id` int(11) NOT NULL,
  `scheduled_post_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `linkedin_post_id` varchar(255) DEFAULT NULL,
  `impressions` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `shares` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `engagement_rate` decimal(5,2) DEFAULT 0.00,
  `last_updated` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_plans`
--

CREATE TABLE `pricing_plans` (
  `id` int(11) NOT NULL,
  `country` varchar(10) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `plan_price` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `features` text DEFAULT NULL,
  `max_posts_per_month` int(11) DEFAULT 50,
  `max_automations` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_plans`
--

INSERT INTO `pricing_plans` (`id`, `country`, `plan_name`, `plan_price`, `currency`, `features`, `max_posts_per_month`, `max_automations`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'us', 'Basic', 19.00, 'USD', '50 posts per month,AI Generation,Email Support', 50, 2, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57'),
(2, 'us', 'Pro', 49.00, 'USD', '200 posts per month,Advanced AI,Priority Support', 200, 5, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57'),
(3, 'us', 'Enterprise', 99.00, 'USD', 'Unlimited posts,All Features,24/7 Support', -1, -1, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57'),
(4, 'in', 'Basic', 1499.00, 'INR', '50 posts per month,AI Generation,Email Support', 50, 2, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57'),
(5, 'in', 'Pro', 3999.00, 'INR', '200 posts per month,Advanced AI,Priority Support', 200, 5, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57'),
(6, 'in', 'Enterprise', 7999.00, 'INR', 'Unlimited posts,All Features,24/7 Support', -1, -1, 1, '2025-09-22 18:10:57', '2025-09-22 18:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_posts`
--

CREATE TABLE `scheduled_posts` (
  `id` int(11) NOT NULL,
  `automation_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('pending','published','failed','cancelled') DEFAULT 'pending',
  `ai_model_used` varchar(50) NOT NULL,
  `linkedin_post_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `attempted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `plan_price` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('active','cancelled','expired') DEFAULT 'active',
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ends_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `payment_gateway` varchar(20) DEFAULT NULL,
  `gateway_subscription_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_notifications`
--

CREATE TABLE `system_notifications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `webhook_logs`
--

CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL,
  `webhook_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `processed` tinyint(1) DEFAULT 0,
  `processing_error` text DEFAULT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `api_settings`
--
ALTER TABLE `api_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_usage`
--
ALTER TABLE `api_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_model` (`customer_id`,`ai_model`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_success` (`success`);

--
-- Indexes for table `automations`
--
ALTER TABLE `automations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_automations_customer_status` (`customer_id`,`status`);

--
-- Indexes for table `content_templates`
--
ALTER TABLE `content_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_content_style` (`content_style`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_oauth` (`oauth_provider`,`oauth_provider_id`);

--
-- Indexes for table `customer_activity`
--
ALTER TABLE `customer_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_action` (`customer_id`,`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_customer_activity_recent` (`customer_id`,`created_at`);

--
-- Indexes for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `customer_automations`
--
ALTER TABLE `customer_automations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer_automations_customer` (`customer_id`);

--
-- Indexes for table `customer_generated_posts`
--
ALTER TABLE `customer_generated_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_automation` (`automation_id`),
  ADD KEY `idx_scheduled` (`scheduled_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_generated_posts_scheduled` (`scheduled_time`);

--
-- Indexes for table `customer_linkedin_tokens`
--
ALTER TABLE `customer_linkedin_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer` (`customer_id`);

--
-- Indexes for table `customer_post_analytics`
--
ALTER TABLE `customer_post_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post` (`post_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer` (`customer_id`),
  ADD KEY `token_index` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payments_customer` (`customer_id`);

--
-- Indexes for table `payment_orders`
--
ALTER TABLE `payment_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_gateway_order` (`gateway_order_id`);

--
-- Indexes for table `post_analytics`
--
ALTER TABLE `post_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post_analytics` (`scheduled_post_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_linkedin_post_id` (`linkedin_post_id`);

--
-- Indexes for table `pricing_plans`
--
ALTER TABLE `pricing_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_country_plan` (`country`,`plan_name`),
  ADD KEY `idx_country` (`country`);

--
-- Indexes for table `scheduled_posts`
--
ALTER TABLE `scheduled_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `automation_id` (`automation_id`),
  ADD KEY `idx_scheduled_time` (`scheduled_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer_automation` (`customer_id`,`automation_id`),
  ADD KEY `idx_pending_posts` (`status`,`scheduled_time`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_subscriptions_customer` (`customer_id`);

--
-- Indexes for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_unread` (`customer_id`,`is_read`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `webhook_logs`
--
ALTER TABLE `webhook_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_webhook_type` (`webhook_type`),
  ADD KEY `idx_processed` (`processed`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `api_usage`
--
ALTER TABLE `api_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `automations`
--
ALTER TABLE `automations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_templates`
--
ALTER TABLE `content_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_activity`
--
ALTER TABLE `customer_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `customer_automations`
--
ALTER TABLE `customer_automations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_generated_posts`
--
ALTER TABLE `customer_generated_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_linkedin_tokens`
--
ALTER TABLE `customer_linkedin_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_post_analytics`
--
ALTER TABLE `customer_post_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_orders`
--
ALTER TABLE `payment_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_analytics`
--
ALTER TABLE `post_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pricing_plans`
--
ALTER TABLE `pricing_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `scheduled_posts`
--
ALTER TABLE `scheduled_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_notifications`
--
ALTER TABLE `system_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `webhook_logs`
--
ALTER TABLE `webhook_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `automation_dashboard`
--
DROP TABLE IF EXISTS `automation_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`qcidb7kof599`@`localhost` SQL SECURITY DEFINER VIEW `automation_dashboard`  AS SELECT `a`.`id` AS `id`, `a`.`customer_id` AS `customer_id`, `a`.`name` AS `name`, `a`.`topic` AS `topic`, `a`.`ai_model` AS `ai_model`, `a`.`status` AS `status`, `a`.`duration_days` AS `duration_days`, `a`.`created_at` AS `created_at`, count(`sp`.`id`) AS `total_scheduled_posts`, count(case when `sp`.`status` = 'published' then 1 end) AS `published_posts`, count(case when `sp`.`status` = 'pending' then 1 end) AS `pending_posts`, count(case when `sp`.`status` = 'failed' then 1 end) AS `failed_posts`, min(case when `sp`.`status` = 'pending' then `sp`.`scheduled_time` end) AS `next_post_time`, max(`sp`.`published_at`) AS `last_published_at` FROM (`automations` `a` left join `scheduled_posts` `sp` on(`a`.`id` = `sp`.`automation_id`)) GROUP BY `a`.`id`, `a`.`customer_id`, `a`.`name`, `a`.`topic`, `a`.`ai_model`, `a`.`status`, `a`.`duration_days`, `a`.`created_at` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_usage`
--
ALTER TABLE `api_usage`
  ADD CONSTRAINT `api_usage_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `automations`
--
ALTER TABLE `automations`
  ADD CONSTRAINT `automations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity`
--
ALTER TABLE `customer_activity`
  ADD CONSTRAINT `customer_activity_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD CONSTRAINT `customer_activity_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_automations`
--
ALTER TABLE `customer_automations`
  ADD CONSTRAINT `customer_automations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_generated_posts`
--
ALTER TABLE `customer_generated_posts`
  ADD CONSTRAINT `customer_generated_posts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_generated_posts_ibfk_2` FOREIGN KEY (`automation_id`) REFERENCES `customer_automations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_linkedin_tokens`
--
ALTER TABLE `customer_linkedin_tokens`
  ADD CONSTRAINT `customer_linkedin_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_post_analytics`
--
ALTER TABLE `customer_post_analytics`
  ADD CONSTRAINT `customer_post_analytics_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_post_analytics_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `customer_generated_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD CONSTRAINT `customer_sessions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_orders`
--
ALTER TABLE `payment_orders`
  ADD CONSTRAINT `payment_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_analytics`
--
ALTER TABLE `post_analytics`
  ADD CONSTRAINT `post_analytics_ibfk_1` FOREIGN KEY (`scheduled_post_id`) REFERENCES `scheduled_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_analytics_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scheduled_posts`
--
ALTER TABLE `scheduled_posts`
  ADD CONSTRAINT `scheduled_posts_ibfk_1` FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scheduled_posts_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD CONSTRAINT `system_notifications_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`qcidb7kof599`@`localhost` EVENT `daily_cleanup` ON SCHEDULE EVERY 1 DAY STARTS '2025-09-25 02:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL CleanupOldData()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
