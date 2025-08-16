<?php
// Social Media Crawler Meta Tag Handler
// Detects social media crawlers and serves them proper meta tags from Supabase data

// Supabase configuration
$supabaseUrl = 'https://exgsbnnyjkjmmxnofnmr.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV4Z3Nibm55amtqbW14bm9mbm1yIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI1ODY1NDksImV4cCI6MjA2ODE2MjU0OX0.r5zPUGKuGS5IrmJovExcmOorLC7q7uCa0vPIdiFKYoE';

// Default meta tags for fallback
$defaultTitle = 'Strata Consulting Engineers - Expert Structural Engineering Services';
$defaultDescription = 'Professional structural engineering consulting services in Phoenix, Arizona. Expert foundation inspections, structural assessments, and engineering solutions.';
$defaultImage = 'https://strataconsultingengineers.com/SCE Logo-2024.png';
$defaultUrl = 'https://strataconsultingengineers.com';

/**
 * Check if the request is from a social media crawler
 */
function isSocialCrawler($userAgent) {
    $crawlers = [
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'SkypeUriPreview',
        'SlackBot',
        'DiscordBot',
        'GoogleBot',
        'bingbot'
    ];
    
    foreach ($crawlers as $crawler) {
        if (stripos($userAgent, $crawler) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Extract blog post slug from the URL path
 */
function extractBlogSlug($path) {
    // Match patterns like /blog/post-slug or /blog/post-slug/
    if (preg_match('/^\/blog\/([^\/]+)\/?$/', $path, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Fetch blog post data from Supabase
 */
function fetchBlogPost($slug, $supabaseUrl, $supabaseKey) {
    $url = $supabaseUrl . '/rest/v1/blog_posts?slug=eq.' . urlencode($slug) . '&status=eq.published&select=*';
    
    $headers = [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
    
    return null;
}

/**
 * Generate HTML with proper meta tags
 */
function generateHTML($title, $description, $image, $url, $type = 'website') {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Basic Meta Tags -->
    <title>' . htmlspecialchars($title) . '</title>
    <meta name="description" content="' . htmlspecialchars($description) . '">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="' . htmlspecialchars($type) . '">
    <meta property="og:title" content="' . htmlspecialchars($title) . '">
    <meta property="og:description" content="' . htmlspecialchars($description) . '">
    <meta property="og:url" content="' . htmlspecialchars($url) . '">
    <meta property="og:site_name" content="Strata Consulting Engineers">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="' . htmlspecialchars($title) . '">
    <meta name="twitter:description" content="' . htmlspecialchars($description) . '">
    
    <!-- Image Meta Tags -->';
    
    if ($image) {
        $html .= '
    <meta property="og:image" content="' . htmlspecialchars($image) . '">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="' . htmlspecialchars($image) . '">';
    }
    
    $html .= '
    
    <!-- Canonical URL -->
    <meta property="og:url" content="' . htmlspecialchars($url) . '">
    
    <!-- Redirect to React App -->
    <script>
        // Only redirect if this is not a crawler request
        if (!/bot|crawler|spider|crawling/i.test(navigator.userAgent)) {
            window.location.replace("' . htmlspecialchars($url) . '");
        }
    </script>
    
    <!-- Fallback refresh for non-JS environments -->
    <noscript>
        <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">
    </noscript>
</head>
<body>
    <h1>' . htmlspecialchars($title) . '</h1>
    <p>' . htmlspecialchars($description) . '</p>
    <p><a href="' . htmlspecialchars($url) . '">Continue to full site</a></p>
</body>
</html>';
    
    return $html;
}

// Main execution
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'strataconsultingengineers.com';
$currentUrl = $protocol . '://' . $host . $requestUri;

// Check if this is a social media crawler
if (!isSocialCrawler($userAgent)) {
    // Not a crawler, redirect to the React app
    header('Location: /');
    exit();
}

// Extract blog slug from URL
$blogSlug = extractBlogSlug($requestUri);

if ($blogSlug) {
    // This is a blog post URL, try to fetch the post data
    $post = fetchBlogPost($blogSlug, $supabaseUrl, $supabaseKey);
    
    if ($post) {
        // Use blog post data for meta tags
        $title = $post['meta_title'] ?: $post['title'];
        $description = $post['meta_description'] ?: $post['excerpt'] ?: $defaultDescription;
        $image = $post['featured_image'] ?: $defaultImage;
        $type = 'article';
        
        // Ensure proper URL format
        $url = $protocol . '://' . $host . '/blog/' . $post['slug'];
        
        echo generateHTML($title, $description, $image, $url, $type);
    } else {
        // Blog post not found, use default meta tags
        echo generateHTML($defaultTitle, $defaultDescription, $defaultImage, $currentUrl);
    }
} else {
    // Not a blog post URL, use default meta tags
    echo generateHTML($defaultTitle, $defaultDescription, $defaultImage, $currentUrl);
}
?>