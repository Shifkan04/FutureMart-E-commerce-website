<?php
// languages.php - Create this file in your root directory

function loadLanguage($lang = 'en_US') {
    $translations = [
        'en_US' => [
            // Navigation
            'dashboard' => 'Dashboard',
            'my_deliveries' => 'My Deliveries',
            'route_map' => 'Route & Map',
            'profile' => 'Profile',
            'messages' => 'Messages',
            'contact' => 'Contact',
            'logout' => 'Logout',
            'delivery_panel' => 'Delivery Panel',
            
            // Profile Page
            'profile_title' => 'Profile',
            'edit_personal_info' => 'Edit Personal Information',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'bio_notes' => 'Bio / Notes',
            'save_changes' => 'Save Changes',
            'email_cannot_change' => 'Email cannot be changed',
            
            // Tabs
            'personal_info' => 'Personal Info',
            'settings' => 'Settings',
            'performance' => 'Performance',
            
            // Settings
            'notification_preferences' => 'Notification Preferences',
            'email_notifications' => 'Email notifications for new deliveries',
            'sms_notifications' => 'SMS notifications for urgent updates',
            'push_notifications' => 'Push notifications and promotional emails',
            'save_notification_prefs' => 'Save Notification Preferences',
            'application_settings' => 'Application Settings',
            'theme' => 'Theme',
            'language' => 'Language',
            'change_password' => 'Change Password',
            'save_settings' => 'Save Settings',
            
            // Theme Options
            'light' => 'Light',
            'dark' => 'Dark',
            'auto' => 'Auto (System)',
            
            // Language Options
            'english' => 'English',
            'sinhala' => 'Sinhala (සිංහල)',
            'tamil' => 'Tamil (தமிழ்)',
            
            // Performance Stats
            'total_deliveries' => 'Total Deliveries',
            'completed_deliveries' => 'Completed Deliveries',
            'on_time_delivery' => 'On-Time Delivery',
            'avg_delivery_time' => 'Avg. Delivery Time',
            'this_month' => 'This Month',
            'deliveries_assigned' => 'Deliveries Assigned',
            'average_time' => 'Average Time',
            'performance_status' => 'Performance Status',
            'complete_more_deliveries' => 'Complete more deliveries to earn badges!',
            
            // Badges
            'century_club' => 'Century Club',
            'punctual_pro' => 'Punctual Pro',
            'top_performer' => 'Top Performer',
            'active_member' => 'Active Member',
            
            // Profile Info
            'id' => 'ID',
            'joined' => 'Joined',
            'status' => 'Status',
            
            // Messages
            'profile_updated' => 'Profile updated successfully!',
            'settings_updated' => 'Settings updated successfully!',
            'picture_updated' => 'Profile picture updated successfully!',
            'notifications_updated' => 'Notification preferences updated successfully!',
            'invalid_file_type' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.',
            'file_size_error' => 'File size must be less than 5MB.',
            'upload_failed' => 'Failed to upload profile picture.',
            'no_file_uploaded' => 'No file uploaded or upload error occurred.',
            
            // Confirm Messages
            'confirm_logout' => 'Are you sure you want to logout?',
            'upload_confirm' => 'Upload this image as your profile picture?',
            
            // Time Units
            'mins' => 'mins',
            'min' => 'min',
        ],
        
        'ta_LK' => [
            // Navigation
            'dashboard' => 'முகப்பு',
            'my_deliveries' => 'எனது விநியோகங்கள்',
            'route_map' => 'வழி & வரைபடம்',
            'profile' => 'சுயவிவரம்',
            'messages' => 'செய்திகள்',
            'contact' => 'தொடர்பு',
            'logout' => 'வெளியேறு',
            'delivery_panel' => 'விநியோக பேனல்',
            
            // Profile Page
            'profile_title' => 'சுயவிவரம்',
            'edit_personal_info' => 'தனிப்பட்ட தகவல்களை திருத்து',
            'first_name' => 'முதல் பெயர்',
            'last_name' => 'கடைசி பெயர்',
            'email' => 'மின்னஞ்சல்',
            'phone' => 'தொலைபேசி',
            'bio_notes' => 'குறிப்புகள்',
            'save_changes' => 'மாற்றங்களை சேமி',
            'email_cannot_change' => 'மின்னஞ்சலை மாற்ற முடியாது',
            
            // Tabs
            'personal_info' => 'தனிப்பட்ட தகவல்',
            'settings' => 'அமைப்புகள்',
            'performance' => 'செயல்திறன்',
            
            // Settings
            'notification_preferences' => 'அறிவிப்பு விருப்பத்தேர்வுகள்',
            'email_notifications' => 'புதிய விநியோகங்களுக்கான மின்னஞ்சல் அறிவிப்புகள்',
            'sms_notifications' => 'அவசர புதுப்பிப்புகளுக்கான SMS அறிவிப்புகள்',
            'push_notifications' => 'புஷ் அறிவிப்புகள் மற்றும் விளம்பர மின்னஞ்சல்கள்',
            'save_notification_prefs' => 'அறிவிப்பு விருப்பங்களை சேமி',
            'application_settings' => 'பயன்பாட்டு அமைப்புகள்',
            'theme' => 'தீம்',
            'language' => 'மொழி',
            'change_password' => 'கடவுச்சொல்லை மாற்று',
            'save_settings' => 'அமைப்புகளை சேமி',
            
            // Theme Options
            'light' => 'ஒளி',
            'dark' => 'இருள்',
            'auto' => 'தானியங்கி',
            
            // Language Options
            'english' => 'ஆங்கிலம் (English)',
            'sinhala' => 'சிங்களம் (සිංහල)',
            'tamil' => 'தமிழ்',
            
            // Performance Stats
            'total_deliveries' => 'மொத்த விநியோகங்கள்',
            'completed_deliveries' => 'நிறைவு செய்த விநியோகங்கள்',
            'on_time_delivery' => 'சரியான நேரத்தில் விநியோகம்',
            'avg_delivery_time' => 'சராசரி விநியோக நேரம்',
            'this_month' => 'இந்த மாதம்',
            'deliveries_assigned' => 'ஒதுக்கப்பட்ட விநியோகங்கள்',
            'average_time' => 'சராசரி நேரம்',
            'performance_status' => 'செயல்திறன் நிலை',
            'complete_more_deliveries' => 'பேட்ஜ்கள் பெற மேலும் விநியோகங்களை முடிக்கவும்!',
            
            // Badges
            'century_club' => 'நூற்றாண்டு கிளப்',
            'punctual_pro' => 'நேரம் தவறாதவர்',
            'top_performer' => 'சிறந்த செயல்திறன்',
            'active_member' => 'செயலில் உள்ள உறுப்பினர்',
            
            // Profile Info
            'id' => 'அடையாள எண்',
            'joined' => 'சேர்ந்தது',
            'status' => 'நிலை',
            
            // Messages
            'profile_updated' => 'சுயவிவரம் வெற்றிகரமாக புதுப்பிக்கப்பட்டது!',
            'settings_updated' => 'அமைப்புகள் வெற்றிகரமாக புதுப்பிக்கப்பட்டன!',
            'picture_updated' => 'சுயவிவர படம் வெற்றிகரமாக புதுப்பிக்கப்பட்டது!',
            'notifications_updated' => 'அறிவிப்பு விருப்பங்கள் வெற்றிகரமாக புதுப்பிக்கப்பட்டன!',
            'invalid_file_type' => 'தவறான கோப்பு வகை. JPG, PNG மற்றும் GIF மட்டுமே அனுமதிக்கப்படும்.',
            'file_size_error' => 'கோப்பு அளவு 5MB க்கும் குறைவாக இருக்க வேண்டும்.',
            'upload_failed' => 'சுயவிவர படத்தை பதிவேற்ற முடியவில்லை.',
            'no_file_uploaded' => 'எந்த கோப்பும் பதிவேற்றப்படவில்லை அல்லது பதிவேற்ற பிழை ஏற்பட்டது.',
            
            // Confirm Messages
            'confirm_logout' => 'நீங்கள் வெளியேற விரும்புகிறீர்களா?',
            'upload_confirm' => 'இந்த படத்தை உங்கள் சுயவிவர படமாக பதிவேற்றவா?',
            
            // Time Units
            'mins' => 'நிமிடங்கள்',
            'min' => 'நிமிடம்',
        ],
        
        'si_LK' => [
            // Navigation
            'dashboard' => 'උපකරණ පුවරුව',
            'my_deliveries' => 'මාගේ බෙදාහැරීම්',
            'route_map' => 'මාර්ගය සහ සිතියම',
            'profile' => 'පැතිකඩ',
            'messages' => 'පණිවිඩ',
            'contact' => 'සම්බන්ධ වන්න',
            'logout' => 'ඉවත් වන්න',
            'delivery_panel' => 'බෙදාහැරීම් පුවරුව',
            
            // Profile Page
            'profile_title' => 'පැතිකඩ',
            'edit_personal_info' => 'පුද්ගලික තොරතුරු සංස්කරණය කරන්න',
            'first_name' => 'මුල් නම',
            'last_name' => 'අවසාන නම',
            'email' => 'ඊමේල්',
            'phone' => 'දුරකථනය',
            'bio_notes' => 'සටහන්',
            'save_changes' => 'වෙනස්කම් සුරකින්න',
            'email_cannot_change' => 'ඊමේල් වෙනස් කළ නොහැක',
            
            // Tabs
            'personal_info' => 'පුද්ගලික තොරතුරු',
            'settings' => 'සැකසුම්',
            'performance' => 'කාර්ය සාධනය',
            
            // Settings
            'notification_preferences' => 'දැනුම්දීම් මනාපයන්',
            'email_notifications' => 'නව බෙදාහැරීම් සඳහා ඊමේල් දැනුම්දීම්',
            'sms_notifications' => 'හදිසි යාවත්කාලීන සඳහා SMS දැනුම්දීම්',
            'push_notifications' => 'තල්ලු දැනුම්දීම් සහ ප්‍රවර්ධන ඊමේල්',
            'save_notification_prefs' => 'දැනුම්දීම් මනාපයන් සුරකින්න',
            'application_settings' => 'යෙදුම් සැකසුම්',
            'theme' => 'තේමාව',
            'language' => 'භාෂාව',
            'change_password' => 'මුරපදය වෙනස් කරන්න',
            'save_settings' => 'සැකසුම් සුරකින්න',
            
            // Theme Options
            'light' => 'ආලෝකය',
            'dark' => 'අඳුරු',
            'auto' => 'ස්වයංක්‍රීය',
            
            // Language Options
            'english' => 'ඉංග්‍රීසි (English)',
            'sinhala' => 'සිංහල',
            'tamil' => 'දෙමළ (தமிழ்)',
            
            // Performance Stats
            'total_deliveries' => 'මුළු බෙදාහැරීම්',
            'completed_deliveries' => 'සම්පූර්ණ කළ බෙදාහැරීම්',
            'on_time_delivery' => 'කාලානුරූප බෙදාහැරීම',
            'avg_delivery_time' => 'සාමාන්‍ය බෙදාහැරීමේ කාලය',
            'this_month' => 'මෙම මාසය',
            'deliveries_assigned' => 'පවරා ඇති බෙදාහැරීම්',
            'average_time' => 'සාමාන්‍ය කාලය',
            'performance_status' => 'කාර්ය සාධන තත්ත්වය',
            'complete_more_deliveries' => 'ලාංඡන ලබා ගැනීමට තවත් බෙදාහැරීම් සම්පූර්ණ කරන්න!',
            
            // Badges
            'century_club' => 'ශත සමාජය',
            'punctual_pro' => 'කාලානුරූප වෘත්තිකයා',
            'top_performer' => 'ඉහළම කාර්ය සාධනය',
            'active_member' => 'ක්‍රියාකාරී සාමාජිකයා',
            
            // Profile Info
            'id' => 'හැඳුනුම්පත',
            'joined' => 'එක්වූ දිනය',
            'status' => 'තත්ත්වය',
            
            // Messages
            'profile_updated' => 'පැතිකඩ සාර්ථකව යාවත්කාලීන කරන ලදී!',
            'settings_updated' => 'සැකසුම් සාර්ථකව යාවත්කාලීන කරන ලදී!',
            'picture_updated' => 'පැතිකඩ පින්තූරය සාර්ථකව යාවත්කාලීන කරන ලදී!',
            'notifications_updated' => 'දැනුම්දීම් මනාපයන් සාර්ථකව යාවත්කාලීන කරන ලදී!',
            'invalid_file_type' => 'වලංගු නොවන ගොනු වර්ගය. JPG, PNG සහ GIF පමණක් අවසර ඇත.',
            'file_size_error' => 'ගොනු ප්‍රමාණය MB 5 ට අඩු විය යුතුය.',
            'upload_failed' => 'පැතිකඩ පින්තූරය උඩුගත කිරීමට අසමත් විය.',
            'no_file_uploaded' => 'කිසිදු ගොනුවක් උඩුගත කර නැත හෝ උඩුගත කිරීමේ දෝෂයක් සිදු විය.',
            
            // Confirm Messages
            'confirm_logout' => 'ඔබට පිටවීමට අවශ්‍ය බව විශ්වාසද?',
            'upload_confirm' => 'මෙම පින්තූරය ඔබේ පැතිකඩ පින්තූරය ලෙස උඩුගත කරන්නද?',
            
            // Time Units
            'mins' => 'විනාඩි',
            'min' => 'විනාඩියක්',
        ]
    ];
    
    return $translations[$lang] ?? $translations['en_US'];
}

function t($key, $lang = null) {
    global $translations;
    
    if ($lang === null) {
        $lang = $_SESSION['user_language'] ?? 'en_US';
    }
    
    if (!isset($translations)) {
        $translations = loadLanguage($lang);
    }
    
    return $translations[$key] ?? $key;
}

// Initialize translations
$current_language = $_SESSION['user_language'] ?? 'en_US';
$translations = loadLanguage($current_language);
?>