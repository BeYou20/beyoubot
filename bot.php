<?php

require __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

// ===================== Bot Configuration =====================
const BOT_TOKEN = os.getenv("TOKEN_POT123");
const ADMIN_ID = 7644806383;
const DATA_DIR = __DIR__ . '/data/';
const COURSES_FILE = DATA_DIR . "courses.json";
const CONVERSATIONS_FILE = DATA_DIR . "conversations.json";
const REGISTRANTS_FILE = DATA_DIR . "registrants.json";

$telegram = new Api(BOT_TOKEN);

// ===================== Data Handling & Directory Setup =====================
function setupDataDirectory() {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    if (!file_exists(COURSES_FILE)) {
        file_put_contents(COURSES_FILE, json_encode(['users' => [], 'courses' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    if (!file_exists(CONVERSATIONS_FILE)) {
        file_put_contents(CONVERSATIONS_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    if (!file_exists(REGISTRANTS_FILE)) {
        file_put_contents(REGISTRANTS_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
setupDataDirectory();

function loadData($file) {
    if (file_exists($file) && filesize($file) > 0) {
        $data = json_decode(file_get_contents($file), true);
        return $data === null ? [] : $data;
    }
    return [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$coursesData = loadData(COURSES_FILE);
$conversations = loadData(CONVERSATIONS_FILE);
$registrants = loadData(REGISTRANTS_FILE);

// ===================== Keyboards =====================
function backToMainKeyboard() {
    return Keyboard::make()->inline()->row(
        Keyboard::inlineButton(['text' => "🏠 رجوع", 'callback_data' => "main_menu"])
    );
}

function mainMenuKeyboard($userId) {
    $keyboard = [
        [Keyboard::inlineButton(['text' => "📚 استعراض الدورات", 'callback_data' => "show_courses"])],
        [Keyboard::inlineButton(['text' => "📞 تواصل معنا", 'callback_data' => "contact_us"])]
    ];
    if ($userId == ADMIN_ID) {
        $keyboard[] = [Keyboard::inlineButton(['text' => "➕ إضافة دورة", 'callback_data' => "add_course"])];
        $keyboard[] = [Keyboard::inlineButton(['text' => "👥 إدارة المتدربين", 'callback_data' => "manage_registrants"])];
    }
    return Keyboard::make()->inline()->rows(...$keyboard);
}

function coursesKeyboard($userId) {
    $keyboard = [];
    $row = [];
    foreach ($GLOBALS['coursesData']['courses'] as $idx => $course) {
        $row[] = Keyboard::inlineButton(['text' => $course['name'], 'callback_data' => "course_{$idx}"]);
        if (count($row) == 3) {
            $keyboard[] = $row;
            $row = [];
        }
    }
    if ($row) {
        $keyboard[] = $row;
    }
    $tail = [Keyboard::inlineButton(['text' => "🏠 رجوع", 'callback_data' => "main_menu"])];
    if ($userId == ADMIN_ID) {
        $tail[] = Keyboard::inlineButton(['text' => "➕ إضافة دورة", 'callback_data' => "add_course"]);
    }
    $keyboard[] = $tail;
    return Keyboard::make()->inline()->rows(...$keyboard);
}

function courseDetailsKeyboard($idx, $isAdmin) {
    $row = [
        Keyboard::inlineButton(['text' => "⬅️ رجوع", 'callback_data' => "show_courses"]),
        Keyboard::inlineButton(['text' => "📥 التسجيل في الدورة", 'callback_data' => "register_{$idx}"])
    ];
    if ($isAdmin) {
        $row[] = Keyboard::inlineButton(['text' => "✏️ تعديل", 'callback_data' => "edit_{$idx}"]);
        $row[] = Keyboard::inlineButton(['text' => "🗑️ حذف", 'callback_data' => "del_{$idx}"]);
    }
    return Keyboard::make()->inline()->row(...$row);
}

function contactKeyboard() {
    return Keyboard::make()->inline()->rows(
        [Keyboard::inlineButton(['text' => "📞 اتصل بنا", 'url' => "tel:+967777612552"])],
        [Keyboard::inlineButton(['text' => "📱 واتساب (1)", 'url' => "https://wa.me/967771901320"])],
        [Keyboard::inlineButton(['text' => "📱 واتساب (2)", 'url' => "https://wa.me/967778185189"])],
        [Keyboard::inlineButton(['text' => "🌐 موقعنا الإلكتروني", 'url' => "http://www.your-website.com"])],
        [Keyboard::inlineButton(['text' => "🏠 رجوع", 'callback_data' => "main_menu"])]
    );
}

function registrantsKeyboard() {
    $keyboard = [];
    foreach ($GLOBALS['registrants'] as $idx => $registrant) {
        $keyboard[] = [Keyboard::inlineButton(['text' => "{$registrant['name']} - {$registrant['course_name']}", 'callback_data' => "registrant_{$idx}"])];
    }
    $keyboard[] = [Keyboard::inlineButton(['text' => "🏠 رجوع", 'callback_data' => "main_menu"])];
    return Keyboard::make()->inline()->rows(...$keyboard);
}

function registrantDetailsKeyboard($idx) {
    return Keyboard::make()->inline()->row(
        Keyboard::inlineButton(['text' => "⬅️ رجوع", 'callback_data' => "manage_registrants"]),
        Keyboard::inlineButton(['text' => "🗑️ حذف", 'callback_data' => "del_registrant_{$idx}"])
    );
}

// ===================== Message and Callback Handlers =====================
$update = $telegram->getWebhookUpdate();
$message = $update->getMessage();
$callbackQuery = $update->getCallbackQuery();

$chatId = $message ? $message->getChat()->getId() : ($callbackQuery ? $callbackQuery->getMessage()->getChat()->getId() : null);
$userId = $message ? $message->getFrom()->getId() : ($callbackQuery ? $callbackQuery->getFrom()->getId() : null);

if (!$chatId) {
    die();
}

// Handle /start command
if ($message && $message->getText() == '/start') {
    if (!in_array($userId, ($coursesData['users'] ?? []))) {
        $coursesData['users'][] = $userId;
        saveData(COURSES_FILE, $coursesData);
        $telegram->sendMessage([
            'chat_id' => ADMIN_ID,
            'text' => "👤 مستخدم جديد دخل البوت:\nID: {$userId}\nName: " . $message->getFrom()->getFirstName()
        ]);
    }
    $telegram->sendMessage([
        'chat_id' => $chatId,
        'text' => "🌟 مرحبًا بك في بوت مؤسسة 'كن أنت للتدريب والتأهيل' 🎓",
        'reply_markup' => mainMenuKeyboard($userId)
    ]);
    unset($conversations[$userId]);
    saveData(CONVERSATIONS_FILE, $conversations);
    die();
}

// Handle callback queries
if ($callbackQuery) {
    $data = $callbackQuery->getData();
    $messageId = $callbackQuery->getMessage()->getMessageId();

    if ($data == 'main_menu') {
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "🏠 القائمة الرئيسية:",
            'reply_markup' => mainMenuKeyboard($userId)
        ]);
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    } elseif ($data == 'show_courses') {
        if (empty($coursesData['courses'])) {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "❌ لا توجد دورات حالياً.",
                'reply_markup' => mainMenuKeyboard($userId)
            ]);
        } else {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "📚 اختر دورة لعرض التفاصيل:",
                'reply_markup' => coursesKeyboard($userId)
            ]);
        }
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    } elseif ($data == 'contact_us') {
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "📱 تواصل معنا مباشرة عبر الأزرار التالية:",
            'reply_markup' => contactKeyboard()
        ]);
    } elseif ($data == 'manage_registrants') {
        if ($userId != ADMIN_ID) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => "❌ صلاحية المدير فقط."]);
        } elseif (empty($registrants)) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => "👥 لا يوجد متدربون مسجلون حالياً.", 'reply_markup' => backToMainKeyboard()]);
        } else {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "👥 قائمة المتدربين المسجلين:",
                'reply_markup' => registrantsKeyboard()
            ]);
        }
    } elseif (strpos($data, 'registrant_') === 0) {
        $idx = (int) explode('_', $data)[1];
        if (isset($registrants[$idx])) {
            $r = $registrants[$idx];
            $text = "👥 تفاصيل المتدرب:\n" .
                    "1- الاسم: {$r['name']}\n" .
                    "2- الجنس: {$r['gender']}\n" .
                    "3- العمر: {$r['age']}\n" .
                    "4- البلد: {$r['country']}\n" .
                    "5- المدينة: {$r['city']}\n" .
                    "6- رقم الهاتف: {$r['phone']}\n" .
                    "7- البريد الإلكتروني: {$r['email']}\n" .
                    "8- معرف التيلجرام: {$r['telegram_username']}\n" .
                    "🆔 ID: {$r['telegram_id']}\n" .
                    "📖 الدورة: {$r['course_name']}";
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'reply_markup' => registrantDetailsKeyboard($idx)
            ]);
        }
    } elseif (strpos($data, 'del_registrant_') === 0) {
        $idx = (int) explode('_', $data)[2];
        if (isset($registrants[$idx])) {
            $name = $registrants[$idx]['name'];
            array_splice($registrants, $idx, 1);
            saveData(REGISTRANTS_FILE, $registrants);
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "🗑️ تم حذف المتدرب '{$name}' بنجاح.",
                'reply_markup' => registrantsKeyboard()
            ]);
        }
    } elseif (strpos($data, 'course_') === 0) {
        $idx = (int) explode('_', $data)[1];
        if (isset($coursesData['courses'][$idx])) {
            $course = $coursesData['courses'][$idx];
            $text = "📖 {$course['name']}\n\n{$course['description']}\n💰 رسوم الدورة: {$course['price']}";
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'reply_markup' => courseDetailsKeyboard($idx, $userId == ADMIN_ID)
            ]);
        }
    } elseif ($data == 'add_course') {
        if ($userId != ADMIN_ID) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => "❌ صلاحية المدير فقط."]);
        } else {
            $conversations[$userId] = ['state' => 'ADD_NAME', 'data' => []];
            saveData(CONVERSATIONS_FILE, $conversations);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل اسم الدورة:"]);
        }
    } elseif (strpos($data, 'register_') === 0) {
        $idx = (int) explode('_', $data)[1];
        $conversations[$userId] = ['state' => 'REGISTER_NAME', 'data' => ['course_name' => $coursesData['courses'][$idx]['name']]];
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل الاسم الثلاثي:", 'reply_markup' => backToMainKeyboard()]);
    } elseif (strpos($data, 'accept_') === 0) {
        $parts = explode('_', $data);
        $targetUserId = (int) $parts[1];
        $courseIdx = (int) $parts[2];
        $conversations[$userId] = ['state' => 'ACCEPT_MESSAGE', 'data' => ['user_id' => $targetUserId, 'course_idx' => $courseIdx, 'message_id' => $messageId, 'chat_id' => $chatId]];
        saveData(CONVERSATIONS_FILE, $conversations);
        $keyboard = Keyboard::make()->inline()->row(Keyboard::inlineButton(['text' => "إلغاء", 'callback_data' => "cancel_action"]));
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم اختيار القبول. الآن، أرسل رسالة القبول للمستخدم:", 'reply_markup' => $keyboard]);
    } elseif (strpos($data, 'reject_') === 0) {
        $parts = explode('_', $data);
        $targetUserId = (int) $parts[1];
        $courseIdx = (int) $parts[2];
        $conversations[$userId] = ['state' => 'REJECT_MESSAGE', 'data' => ['user_id' => $targetUserId, 'course_idx' => $courseIdx, 'message_id' => $messageId, 'chat_id' => $chatId]];
        saveData(CONVERSATIONS_FILE, $conversations);
        $keyboard = Keyboard::make()->inline()->row(Keyboard::inlineButton(['text' => "إلغاء", 'callback_data' => "cancel_action"]));
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "❌ تم اختيار الرفض. الآن، أرسل رسالة الرفض للمستخدم:", 'reply_markup' => $keyboard]);
    } elseif ($data == 'cancel_action') {
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم إلغاء العملية.", 'reply_markup' => backToMainKeyboard()]);
    } elseif (strpos($data, 'edit_') === 0) {
        $idx = (int) explode('_', $data)[1];
        $conversations[$userId] = ['state' => 'EDIT_SELECT', 'data' => ['idx' => $idx]];
        saveData(CONVERSATIONS_FILE, $conversations);
        $keyboard = Keyboard::make()->inline()->rows(
            [Keyboard::inlineButton(['text' => "تعديل الاسم", 'callback_data' => "edit_field_name"])],
            [Keyboard::inlineButton(['text' => "تعديل الوصف", 'callback_data' => "edit_field_description"])],
            [Keyboard::inlineButton(['text' => "تعديل الرسوم", 'callback_data' => "edit_field_price"])]
        );
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✏️ اختر ما تريد تعديله:", 'reply_markup' => $keyboard]);
    } elseif (strpos($data, 'edit_field_') === 0) {
        $field = explode('_', $data)[2];
        $conversations[$userId]['state'] = 'EDIT_SAVE_' . strtoupper($field);
        $conversations[$userId]['data']['field'] = $field;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل القيمة الجديدة لـ {$field}:", 'reply_markup' => Keyboard::make()->inline()->row(Keyboard::inlineButton(['text' => "إلغاء", 'callback_data' => "cancel_action"]))]);
    } elseif (strpos($data, 'del_') === 0) {
        $idx = (int) explode('_', $data)[1];
        $conversations[$userId] = ['state' => 'DELETE_CONFIRM', 'data' => ['idx' => $idx]];
        saveData(CONVERSATIONS_FILE, $conversations);
        $courseName = $coursesData['courses'][$idx]['name'];
        $keyboard = Keyboard::make()->inline()->row(
            Keyboard::inlineButton(['text' => "✅ تأكيد الحذف", 'callback_data' => "confirm_delete"]),
            Keyboard::inlineButton(['text' => "❌ إلغاء", 'callback_data' => "cancel_action"])
        );
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "⚠️ هل أنت متأكد من حذف دورة '{$courseName}'؟", 'reply_markup' => $keyboard]);
    } elseif ($data == 'confirm_delete') {
        $idx = $conversations[$userId]['data']['idx'];
        if (isset($coursesData['courses'][$idx])) {
            $courseName = $coursesData['courses'][$idx]['name'];
            array_splice($coursesData['courses'], $idx, 1);
            saveData(COURSES_FILE, $coursesData);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => "🗑️ تم حذف دورة '{$courseName}' بنجاح.", 'reply_markup' => backToMainKeyboard()]);
        }
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    }
    die();
}

// ===================== Conversation Handling =====================
if ($message && isset($conversations[$userId])) {
    $state = $conversations[$userId]['state'];
    $text = $message->getText();
    $registerData = $conversations[$userId]['data'];

    if ($state == 'ADD_NAME') {
        $registerData['name'] = $text;
        $conversations[$userId]['state'] = 'ADD_DESC';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل وصف الدورة:"]);
    } elseif ($state == 'ADD_DESC') {
        $registerData['description'] = $text;
        $conversations[$userId]['state'] = 'ADD_PRICE';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "💰 أرسل رسوم الدورة:"]);
    } elseif ($state == 'ADD_PRICE') {
        $registerData['price'] = $text;
        $coursesData['courses'][] = $registerData;
        saveData(COURSES_FILE, $coursesData);
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم إضافة الدورة بنجاح!", 'reply_markup' => backToMainKeyboard()]);
    } elseif ($state == 'REGISTER_NAME') {
        $registerData['name'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_GENDER';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل الجنس:"]);
    } elseif ($state == 'REGISTER_GENDER') {
        $registerData['gender'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_AGE';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل العمر:"]);
    } elseif ($state == 'REGISTER_AGE') {
        $registerData['age'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_COUNTRY';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل البلد:"]);
    } elseif ($state == 'REGISTER_COUNTRY') {
        $registerData['country'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_CITY';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل المدينة:"]);
    } elseif ($state == 'REGISTER_CITY') {
        $registerData['city'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_PHONE';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل رقم الهاتف (يجب أن يكون واتساب):"]);
    } elseif ($state == 'REGISTER_PHONE') {
        $registerData['phone'] = $text;
        $conversations[$userId]['state'] = 'REGISTER_EMAIL';
        $conversations[$userId]['data'] = $registerData;
        saveData(CONVERSATIONS_FILE, $conversations);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "📝 أرسل البريد الإلكتروني:"]);
    } elseif ($state == 'REGISTER_EMAIL') {
        $registerData['email'] = $text;
        $registerData['telegram_username'] = $message->getFrom()->getUsername() ?? "❌ لا يوجد";
        $registerData['telegram_id'] = $userId;
        $registrants[] = $registerData;
        saveData(REGISTRANTS_FILE, $registrants);

        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم إرسال جميع البيانات إلى الإدارة، سيتم التواصل معك في أقرب وقت ممكن.", 'reply_markup' => backToMainKeyboard()]);

        $keyboard = Keyboard::make()->inline()->row(
            Keyboard::inlineButton(['text' => "✅ قبول", 'callback_data' => "accept_{$userId}_{$registerData['course_name']}"]),
            Keyboard::inlineButton(['text' => "❌ رفض", 'callback_data' => "reject_{$userId}_{$registerData['course_name']}"])
        );
        $adminText = "📥 طلب انضمام جديد\n" .
                     "📖 الدورة: {$registerData['course_name']}\n" .
                     "1- الاسم الثلاثي: {$registerData['name']}\n" .
                     "2- الجنس: {$registerData['gender']}\n" .
                     "3- العمر: {$registerData['age']}\n" .
                     "4- البلد: {$registerData['country']}\n" .
                     "5- المدينة: {$registerData['city']}\n" .
                     "6- رقم الهاتف: {$registerData['phone']}\n" .
                     "7- البريد الإلكتروني: {$registerData['email']}\n" .
                     "8- معرف التيلجرام: @{$registerData['telegram_username']}\n" .
                     "🆔 ID: {$registerData['telegram_id']}";
        $telegram->sendMessage(['chat_id' => ADMIN_ID, 'text' => $adminText, 'reply_markup' => $keyboard]);

        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    } elseif ($state == 'ACCEPT_MESSAGE') {
        $data = $conversations[$userId]['data'];
        $targetUserId = $data['user_id'];
        $courseIdx = $data['course_idx'];
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $courseName = $coursesData['courses'][$courseIdx]['name'];

        $telegram->sendMessage(['chat_id' => $targetUserId, 'text' => $text]);
        $telegram->editMessageText(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => "✅ تم القبول في دورة: {$courseName}\n\n**الرسالة المرسلة:**\n{$text}"]);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم إرسال رسالة القبول بنجاح.", 'reply_markup' => backToMainKeyboard()]);
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    } elseif ($state == 'REJECT_MESSAGE') {
        $data = $conversations[$userId]['data'];
        $targetUserId = $data['user_id'];
        $courseIdx = $data['course_idx'];
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $courseName = $coursesData['courses'][$courseIdx]['name'];

        $telegram->sendMessage(['chat_id' => $targetUserId, 'text' => $text]);
        $telegram->editMessageText(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => "❌ تم الرفض في دورة: {$courseName}\n\n**الرسالة المرسلة:**\n{$text}"]);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم إرسال رسالة الرفض بنجاح.", 'reply_markup' => backToMainKeyboard()]);
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    } elseif (strpos($state, 'EDIT_SAVE_') === 0) {
        $field = strtolower(explode('_', $state)[2]);
        $idx = $conversations[$userId]['data']['idx'];
        $coursesData['courses'][$idx][$field] = $text;
        saveData(COURSES_FILE, $coursesData);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "✅ تم تعديل {$field} بنجاح.", 'reply_markup' => backToMainKeyboard()]);
        unset($conversations[$userId]);
        saveData(CONVERSATIONS_FILE, $conversations);
    }
}
?>
