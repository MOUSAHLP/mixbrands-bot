<?php
// test
// ===== بوت إضافة المنتجات مع نظام كلمة المرور =====
// https: //api.telegram.org/bot7781767577:AAHOC7H2bDmYQM_5JnbwDQl90JYoy2CNdq4/setWebhook?url=https://mixbrands.shop/bot.php

// ===== إعدادات البوت =====
$telegramToken = '7781767577:AAHOC7H2bDmYQM_5JnbwDQl90JYoy2CNdq4';
$woocommerceUrl = 'https://mixbrands.shop';
$consumerKey = 'ck_9e99f102007666b75a2df551657d471374deae3a';
$consumerSecret = 'cs_09e34886eae626acf3403fae4e7bb096d90a5898';

// ===== نظام كلمة المرور =====
$botPassword = 'mixbrands123456'; // كلمة المرور للبوت - يمكنك تغييرها هنا
// 💡 لتغيير كلمة المرور، قم بتعديل القيمة أعلاه

$dataFile = __DIR__ . '/temp_data.json';
$authorizedUsersFile = __DIR__ . '/authorized_users.json';

// إنشاء ملف المستخدمين المصرح لهم إذا لم يكن موجوداً
if (!file_exists($authorizedUsersFile)) {
    file_put_contents($authorizedUsersFile, json_encode([]));
}

if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
$usersData = loadDataWithLock($dataFile);
$authorizedUsers = json_decode(file_get_contents($authorizedUsersFile), true) ?: [];

$content = file_get_contents("php://input");
$update = json_decode($content, true);
writeLog("Received update: " . json_encode($update));

// ===== التحقق من كلمة المرور =====
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $userId = $update["message"]["from"]["id"];
    
    // التحقق من أن المستخدم مصرح له
    if (!isUserAuthorized($userId, $authorizedUsers)) {
        if (isset($update["message"]["text"])) {
            $text = trim($update["message"]["text"]);
            
            // التحقق من كلمة المرور
            if ($text === $botPassword) {
                // إضافة المستخدم للمستخدمين المصرح لهم
                $authorizedUsers[$userId] = [
                    'chat_id' => $chatId,
                    'authorized_at' => date('Y-m-d H:i:s'),
                    'username' => $update["message"]["from"]["username"] ?? 'Unknown'
                ];
                saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
                
                sendTelegramMessage($chatId, "✅ تم تفعيل الوصول بنجاح!\n\nمرحباً بك في بوت إضافة المنتجات إلى متجرك!\n\nأرسل /start للبدء.\n\n🔒 كلمة المرور: $botPassword");
                return;
            } else {
                sendTelegramMessage($chatId, "🔒 هذا البوت محمي بكلمة مرور.\n\nالرجاء إدخال كلمة المرور للوصول:\n\n💡 إذا كنت لا تعرف كلمة المرور، تواصل مع المدير.");
                return;
            }
        } else {
            // إذا لم يكن نص، أرسل رسالة طلب كلمة المرور
            sendTelegramMessage($chatId, "🔒 هذا البوت محمي بكلمة مرور.\n\nالرجاء إدخال كلمة المرور للوصول:\n\n💡 إذا كنت لا تعرف كلمة المرور، تواصل مع المدير.");
            return;
        }
    }
}

// أيضاً التحقق من callback_query
if (isset($update["callback_query"])) {
    $chatId = $update["callback_query"]["message"]["chat"]["id"];
    $userId = $update["callback_query"]["from"]["id"];
    
    // التحقق من أن المستخدم مصرح له
    if (!isUserAuthorized($userId, $authorizedUsers)) {
        sendTelegramMessage($chatId, "🔒 هذا البوت محمي بكلمة مرور.\n\nالرجاء إدخال كلمة المرور للوصول:\n\n💡 إذا كنت لا تعرف كلمة المرور، تواصل مع المدير.");
        return;
    }
}

// دوال إدارة التصريح (مع نظام كلمة المرور)
function isUserAuthorized($userId, $authorizedUsers) {
    return isset($authorizedUsers[$userId]);
}

function verifyPassword($userId, $password) {
    global $botPassword;
    return $password === $botPassword;
}

function removeUserAuthorization($userId) {
    global $authorizedUsers, $authorizedUsersFile;
    if (isset($authorizedUsers[$userId])) {
        unset($authorizedUsers[$userId]);
        saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
        return true;
    }
    return false;
}

// لا تُقرأ php://input مرة ثانية - تُقرأ مرة واحدة فقط في أعلى الملف وإلا تصبح $update فارغة ولا تُحفظ البيانات

    // دعم استقبال callback_query
    if (isset($update["callback_query"])) {
        $callback = $update["callback_query"];
        $chatId = $callback["message"]["chat"]["id"];
        $userId = (string) $callback["from"]["id"];
        $data = $callback["data"];
        $messageId = $callback["message"]["message_id"];
        
        writeLog("Processing callback_query - User: $userId, Data: $data");
    
    if (!isset($usersData[$userId])) {
        $usersData[$userId] = [
            "product" => [],
            "images" => [],
            "step" => null,
            "edit_mode" => null,
            "attributes" => [],
            "tags" => [],
            "description" => "",
            "brand" => "",
            "color_quantities" => [] // إضافة مصفوفة لتخزين كميات الألوان
        ];
        writeLog("Created new user data for user: $userId");
    }

    // التحقق من التصريح تم في بداية الملف

    // معالجة اختيار التصنيف
    if (strpos($data, 'cat_') === 0) {
        $catId = intval(str_replace('cat_', '', $data));
        writeLog("Processing category selection. Category ID: " . $catId);
        
        $categories = getAllCategories();
        foreach ($categories as $cat) {
            if ($cat['id'] == $catId) {
                $usersData[$userId]["product"]["category"] = $cat['name'];
                $usersData[$userId]["step"] = "choose_brand";
                writeLog("Before save category - userId: $userId, category: " . $cat['name']);
                saveData($usersData, $dataFile);
                writeLog("After save category - Saved category for user $userId: " . $cat['name']);
                // التحقق من الحفظ
                $verifyData = loadDataWithLock($dataFile);
                if (isset($verifyData[$userId])) {
                    writeLog("Verification - Category saved correctly: " . ($verifyData[$userId]["product"]["category"] ?? "NOT FOUND"));
                } else {
                    writeLog("ERROR - User data not found after save category! userId: $userId");
                }
                
                // جلب البراندات
                $brands = getAllBrands();
                $brandButtons = [];
                $tempButtons = [];
                foreach ($brands as $brand) {
                    $tempButtons[] = ["text" => $brand['name'], "callback_data" => 'brand_' . $brand['id']];
                    if (count($tempButtons) == 2) {
                        $brandButtons[] = $tempButtons;
                        $tempButtons = [];
                    }
                }
                if (!empty($tempButtons)) {
                    $brandButtons[] = $tempButtons;
                }
                sendTelegramInlineKeyboard($chatId, "🏷️ اختر الماركة:", $brandButtons);
                writeLog("Sent brand selection keyboard");
                break;
            }
        }
        return;
    }

    // معالجة اختيار البراند
    if (strpos($data, 'brand_') === 0) {
        $brandId = intval(str_replace('brand_', '', $data));
        writeLog("Processing brand selection. Brand ID: " . $brandId);
        
        $brands = getAllBrands();
        foreach ($brands as $brand) {
            if ($brand['id'] == $brandId) {
                $usersData[$userId]["brand"] = $brand['name'];
                $usersData[$userId]["step"] = "choose_color";
                writeLog("Before save brand - userId: $userId, brand: " . $brand['name']);
                saveData($usersData, $dataFile);
                writeLog("After save brand - Saved brand for user $userId: " . $brand['name']);
                // التحقق من الحفظ
                $verifyData = loadDataWithLock($dataFile);
                if (isset($verifyData[$userId])) {
                    writeLog("Verification - Brand saved correctly: " . ($verifyData[$userId]["brand"] ?? "NOT FOUND"));
                } else {
                    writeLog("ERROR - User data not found after save brand! userId: $userId");
                }
                
                sendTelegramMessage($chatId, "✅ تم اختيار الماركة: " . $brand['name']);
                
                // جلب الألوان من WooCommerce
                $colors = getAttributeTerms('pa_color');
                $colorButtons = [];
                $tempButtons = [];
                
                foreach ($colors as $color) {
                    $tempButtons[] = ["text" => $color['name'], "callback_data" => 'color_' . $color['name']];
                    if (count($tempButtons) == 2) {
                        $colorButtons[] = $tempButtons;
                        $tempButtons = [];
                    }
                }
                
                if (!empty($tempButtons)) {
                    $colorButtons[] = $tempButtons;
                }
                
                if (empty($colorButtons)) {
                    sendTelegramMessage($chatId, "⚠️ لم يتم العثور على ألوان في الموقع.");
                    $usersData[$userId]["step"] = "choose_size";
                    saveData($usersData, $dataFile);
                    
                    // جلب المقاسات من WooCommerce
                    $sizes = getAttributeTerms('pa_size');
                    
                    if (empty($sizes)) {
                        // إذا لم تكن هناك مقاسات، انتقل مباشرة إلى الصور
                        $usersData[$userId]["step"] = "images";
                        saveData($usersData, $dataFile);
                        sendTelegramMessage($chatId, "📸 لم يتم العثور على مقاسات. يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
                        
                        // إعادة عرض لوحة المفاتيح الرئيسية
                        $mainKeyboard = [
                            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                            ['❌ إلغاء المنتج']
                        ];
                        
                        $replyMarkup = [
                            'keyboard' => $mainKeyboard,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false
                        ];
                        
                        sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                        return;
                    }
                    
                    $sizeButtons = createSizeButtons($sizes, [], 0);
                    sendTelegramInlineKeyboard($chatId, "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار", $sizeButtons);
                    return;
                }
                
                $colorButtons[] = [["text" => "✅ تم اختيار الألوان", "callback_data" => "done_colors"]];
                $colorButtons[] = [["text" => "📊 تحديد الكميات", "callback_data" => "set_quantities"]];
                
                sendTelegramInlineKeyboard($chatId, "🎨 اختر الألوان المطلوبة (يمكنك اختيار أكثر من لون):\n💡 اضغط على اللون مرة أخرى لإزالته من الاختيار\n📊 يمكنك تحديد الكمية لكل لون بعد اختيار الألوان", $colorButtons);
                writeLog("Sent color selection keyboard");
                break;
            }
        }
        return;
    }

    // معالجة اختيار اللون
    if (strpos($data, 'color_') === 0) {
        $selectedColor = substr($data, 6); // إزالة 'color_' من بداية النص
        writeLog("Color selection: " . $selectedColor);
        
        if (!isset($usersData[$userId]["attributes"]["color"])) {
            $usersData[$userId]["attributes"]["color"] = [];
        }
        
        // إضافة أو إزالة اللون من القائمة
        $colorIndex = array_search($selectedColor, $usersData[$userId]["attributes"]["color"]);
        if ($colorIndex !== false) {
            // إذا كان اللون موجود، نقوم بإزالته
            array_splice($usersData[$userId]["attributes"]["color"], $colorIndex, 1);
            writeLog("Removed color: " . $selectedColor);
        } else {
            // إذا لم يكن اللون موجود، نقوم بإضافته
            $usersData[$userId]["attributes"]["color"][] = $selectedColor;
            writeLog("Added color: " . $selectedColor);
        }
        saveData($usersData, $dataFile);
        
        // إعادة عرض لوحة الألوان مع تحديث الحالة
        $colors = getAttributeTerms('pa_color');
        $colorButtons = [];
        $tempButtons = [];
        
        foreach ($colors as $color) {
            $isSelected = in_array($color['name'], $usersData[$userId]["attributes"]["color"]);
            $buttonText = $color['name'] . ($isSelected ? ' ✓' : '');
            $tempButtons[] = ["text" => $buttonText, "callback_data" => 'color_' . $color['name']];
            if (count($tempButtons) == 2) {
                $colorButtons[] = $tempButtons;
                $tempButtons = [];
            }
        }
        
        if (!empty($tempButtons)) {
            $colorButtons[] = $tempButtons;
        }
        
        $colorButtons[] = [["text" => "✅ تم اختيار الألوان", "callback_data" => "done_colors"]];
        $colorButtons[] = [["text" => "📊 تحديد الكميات", "callback_data" => "set_quantities"]];
        
        // عرض الألوان المختارة حالياً
        $selectedColorsText = "";
        if (empty($usersData[$userId]["attributes"]["color"])) {
            $selectedColorsText = "لم يتم اختيار أي لون بعد";
        } else {
            $selectedColorsArray = [];
            foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                $selectedColorsArray[] = $color;
            }
            $selectedColorsText = "الألوان المختارة: " . implode("، ", $selectedColorsArray);
        }
        
        $message = $selectedColorsText . "\n\n";
        $message .= "🎨 اختر الألوان المطلوبة (يمكنك اختيار أكثر من لون):\n";
        $message .= "💡 اضغط على اللون مرة أخرى لإزالته من الاختيار\n📊 يمكنك تحديد الكمية لكل لون بعد اختيار الألوان";
        
        sendTelegramInlineKeyboard($chatId, $message, $colorButtons);
        writeLog("Updated color selection keyboard");
        return;
    }

    // معالجة اختيار المقاس (يجب أن تكون بعد معالجة التنقل بين الصفحات)
    if (strpos($data, 'size_') === 0 && strpos($data, 'size_page_') !== 0) {
        $selectedSize = substr($data, 5); // إزالة 'size_' من بداية النص
        writeLog("Size selection callback received: " . $selectedSize);
        writeLog("User data before size selection: " . json_encode($usersData[$userId]));
        
        if (!isset($usersData[$userId]["attributes"]["size"])) {
            $usersData[$userId]["attributes"]["size"] = [];
        }
        
        // إضافة أو إزالة المقاس من القائمة
        $sizeIndex = array_search($selectedSize, $usersData[$userId]["attributes"]["size"]);
        if ($sizeIndex !== false) {
            // إذا كان المقاس موجود، نقوم بإزالته
            array_splice($usersData[$userId]["attributes"]["size"], $sizeIndex, 1);
            writeLog("Removed size: " . $selectedSize);
            writeLog("Current sizes after remove: " . json_encode($usersData[$userId]["attributes"]["size"]));
        } else {
            // إذا لم يكن المقاس موجود، نقوم بإضافته
            $usersData[$userId]["attributes"]["size"][] = $selectedSize;
            writeLog("Added size: " . $selectedSize);
            writeLog("Current sizes after add: " . json_encode($usersData[$userId]["attributes"]["size"]));
        }
        saveData($usersData, $dataFile);
        
        // إعادة عرض لوحة المقاسات مع تحديث الحالة
        $sizes = getAttributeTerms('pa_size');
        
        // تحديد الصفحة الحالية بناءً على المقاس المختار
        $currentPage = 0;
        $itemsPerPage = 12;
        $totalPages = ceil(count($sizes) / $itemsPerPage);
        
        // البحث عن المقاس المختار لتحديد الصفحة
        for ($i = 0; $i < $totalPages; $i++) {
            $startIndex = $i * $itemsPerPage;
            $pageItems = array_slice($sizes, $startIndex, $itemsPerPage);
            foreach ($pageItems as $size) {
                if ($size['name'] === $selectedSize) {
                    $currentPage = $i;
                    break 2;
                }
            }
        }
        
        $sizeButtons = createSizeButtons($sizes, $usersData[$userId]["attributes"]["size"], $currentPage);
        
        // عرض المقاسات المختارة حالياً
        $selectedSizes = empty($usersData[$userId]["attributes"]["size"]) ? 
            "لم يتم اختيار أي مقاس بعد" : 
            "المقاسات المختارة: " . implode("، ", $usersData[$userId]["attributes"]["size"]);
        
        $message = $selectedSizes . "\n\n";
        $message .= "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n";
        $message .= "💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n";
        $message .= "⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات";
        
        sendTelegramInlineKeyboard($chatId, $message, $sizeButtons);
        writeLog("Updated size selection keyboard with message: " . $message);
        writeLog("Size buttons: " . json_encode($sizeButtons));
        writeLog("Current page: " . $currentPage);
        return;
    }

    // إنهاء اختيار الألوان
    if ($data === "done_colors") {
        writeLog("Color selection completed");
        
        if (empty($usersData[$userId]["attributes"]["color"])) {
            writeLog("No colors selected");
            sendTelegramMessage($chatId, "⚠️ الرجاء اختيار لون واحد على الأقل");
            return;
        }

        // عرض الألوان المختارة
        $selectedColorsArray = [];
        foreach ($usersData[$userId]["attributes"]["color"] as $color) {
            $selectedColorsArray[] = $color;
        }
        $selectedColors = implode("، ", $selectedColorsArray);
        
        sendTelegramMessage($chatId, "✅ تم اختيار الألوان التالية:\n" . $selectedColors);

        // بدء حلقة طلب الكميات لكل لون (LOOP)
        $usersData[$userId]["quantity_loop"] = true;
        $usersData[$userId]["quantity_loop_index"] = 0;
        $firstColor = $usersData[$userId]["attributes"]["color"][0];
        $usersData[$userId]["current_color"] = $firstColor;
        $usersData[$userId]["step"] = "quantity_input";
        saveData($usersData, $dataFile);

        $currentQuantity = isset($usersData[$userId]["color_quantities"][$firstColor]) ? $usersData[$userId]["color_quantities"][$firstColor] : 0;
        sendTelegramMessage($chatId, "📊 الرجاء إدخال الكمية للون '$firstColor':\n💡 الكمية الحالية: $currentQuantity\n💡 أدخل رقم صحيح (مثال: 5) أو 0 إذا لم ترد بيع هذا اللون");
        return;
    }

    // تحديد الكميات للألوان
    if ($data === "set_quantities") {
        writeLog("Setting quantities for colors");
        
        if (empty($usersData[$userId]["attributes"]["color"])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء اختيار ألوان أولاً قبل تحديد الكميات");
            return;
        }

        $usersData[$userId]["step"] = "set_quantities";
        saveData($usersData, $dataFile);
        
                                $quantityButtons = [];
                    foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                        $currentQuantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
                        $quantityButtons[] = [["text" => "🎨 $color: $currentQuantity قطعة", "callback_data" => "quantity_color_" . $color]];
                    }
            
        $quantityButtons[] = [["text" => "✅ تم تحديد الكميات", "callback_data" => "done_quantities"]];
        $quantityButtons[] = [["text" => "⬅️ رجوع", "callback_data" => "back_to_colors"]];
        
        sendTelegramInlineKeyboard($chatId, "📊 حدد الكمية لكل لون:\n💡 اضغط على اللون لتغيير كميته", $quantityButtons);
        return;
    }

    // تحديد كمية لون معين
    if (strpos($data, 'quantity_color_') === 0) {
        $colorName = substr($data, 15); // إزالة 'quantity_color_' من بداية النص
        writeLog("Setting quantity for color: " . $colorName);
        
        $usersData[$userId]["step"] = "quantity_input";
        $usersData[$userId]["current_color"] = $colorName;
        saveData($usersData, $dataFile);
        
        $currentQuantity = isset($usersData[$userId]["color_quantities"][$colorName]) ? $usersData[$userId]["color_quantities"][$colorName] : 0;
        
        sendTelegramMessage($chatId, "📊 الرجاء إدخال الكمية للون '$colorName':\n💡 الكمية الحالية: $currentQuantity\n💡 أدخل رقم صحيح (مثال: 5) أو 0 إذا لم ترد بيع هذا اللون");
        return;
    }

    // إنهاء تحديد الكميات
    if ($data === "done_quantities") {
        writeLog("Quantity selection completed");
        
        $usersData[$userId]["step"] = "choose_size";
        saveData($usersData, $dataFile);
        
        // عرض الكميات المحددة
        $quantitySummary = "📊 الكميات المحددة لكل لون:\n";
        foreach ($usersData[$userId]["attributes"]["color"] as $color) {
            $quantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
            $quantitySummary .= "🎨 $color: $quantity قطعة\n";
        }
        
        sendTelegramMessage($chatId, $quantitySummary);
        
        // جلب المقاسات من WooCommerce
        $sizes = getAttributeTerms('pa_size');
        
        if (empty($sizes)) {
            // إذا لم تكن هناك مقاسات، انتقل مباشرة إلى الصور
            $usersData[$userId]["step"] = "images";
            saveData($usersData, $dataFile);
            sendTelegramMessage($chatId, "📸 لم يتم العثور على مقاسات. يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
            
            // إعادة عرض لوحة المفاتيح الرئيسية
            $mainKeyboard = [
                ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                ['❌ إلغاء المنتج']
            ];
            
            $replyMarkup = [
                'keyboard' => $mainKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            
            sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
            return;
        }
        
        $sizeButtons = createSizeButtons($sizes, [], 0);
        sendTelegramInlineKeyboard($chatId, "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات", $sizeButtons);
        return;
    }

    // العودة لاختيار الألوان
    if ($data === "back_to_colors") {
        writeLog("Back to color selection");
        
        $usersData[$userId]["step"] = "choose_color";
        saveData($usersData, $dataFile);
        
        // جلب الألوان من WooCommerce
        $colors = getAttributeTerms('pa_color');
        $colorButtons = [];
        $tempButtons = [];
        
        foreach ($colors as $color) {
            $isSelected = in_array($color['name'], $usersData[$userId]["attributes"]["color"]);
            $buttonText = $color['name'] . ($isSelected ? ' ✓' : '');
            $tempButtons[] = ["text" => $buttonText, "callback_data" => 'color_' . $color['name']];
            if (count($tempButtons) == 2) {
                $colorButtons[] = $tempButtons;
                $tempButtons = [];
            }
        }
        
        if (!empty($tempButtons)) {
            $colorButtons[] = $tempButtons;
        }
        
        $colorButtons[] = [["text" => "✅ تم اختيار الألوان", "callback_data" => "done_colors"]];
        $colorButtons[] = [["text" => "📊 تحديد الكميات", "callback_data" => "set_quantities"]];
        
        sendTelegramInlineKeyboard($chatId, "🎨 اختر الألوان المطلوبة (يمكنك اختيار أكثر من لون):\n💡 اضغط على اللون مرة أخرى لإزالته من الاختيار\n📊 يمكنك تحديد الكمية لكل لون بعد اختيار الألوان", $colorButtons);
        return;
    }

    // التنقل بين صفحات المقاسات
    if (strpos($data, 'size_page_') === 0) {
        $page = intval(str_replace('size_page_', '', $data));
        writeLog("Size page navigation to page: " . $page);
        
        // التحقق من صحة رقم الصفحة
        $sizes = getAttributeTerms('pa_size');
        $itemsPerPage = 12;
        $totalPages = ceil(count($sizes) / $itemsPerPage);
        
        if ($page < 0 || $page >= $totalPages) {
            $page = 0; // العودة للصفحة الأولى إذا كان الرقم غير صحيح
        }
        
        // إعادة عرض أزرار المقاسات مع الصفحة المحددة
        $sizeButtons = createSizeButtons($sizes, $usersData[$userId]["attributes"]["size"] ?? [], $page);
        
        $selectedSizes = empty($usersData[$userId]["attributes"]["size"]) ? 
            "لم يتم اختيار أي مقاس بعد" : 
            "المقاسات المختارة: " . implode("، ", $usersData[$userId]["attributes"]["size"]);
        
        $message = $selectedSizes . "\n\n";
        $message .= "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n";
        $message .= "💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n";
        $message .= "⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات";
        
        sendTelegramInlineKeyboard($chatId, $message, $sizeButtons);
        writeLog("Displayed page " . ($page + 1) . " of " . $totalPages);
        return;
    }

    // إنهاء اختيار المقاسات
    if ($data === "done_sizes") {
        writeLog("Done sizes callback received");
        writeLog("User data in done_sizes: " . json_encode($usersData[$userId]));
        
        if (empty($usersData[$userId]["attributes"]["size"])) {
            writeLog("No sizes selected");
            sendTelegramMessage($chatId, "⚠️ لم يتم اختيار أي مقاس. يمكنك اختيار مقاسات أو الضغط على 'تخطي المقاسات'");
            return;
        }

        $usersData[$userId]["step"] = "images";
        saveData($usersData, $dataFile);
        
        $selectedSizes = implode("، ", $usersData[$userId]["attributes"]["size"]);
        sendTelegramMessage($chatId, "✅ تم اختيار المقاسات التالية: " . $selectedSizes);
        sendTelegramMessage($chatId, "📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
        
        // إعادة عرض لوحة المفاتيح الرئيسية
        $mainKeyboard = [
            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
            ['❌ إلغاء المنتج']
        ];
        
        $replyMarkup = [
            'keyboard' => $mainKeyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
        writeLog("Moving to images step");
        return;
    }

    // مسح جميع المقاسات المختارة
    if ($data === "clear_sizes") {
        writeLog("Clear sizes callback received");
        $usersData[$userId]["attributes"]["size"] = [];
        saveData($usersData, $dataFile);
        
        // إعادة عرض أزرار المقاسات
        $sizes = getAttributeTerms('pa_size');
        $sizeButtons = createSizeButtons($sizes, [], 0);
        
        $message = "🗑️ تم مسح جميع المقاسات المختارة\n\n";
        $message .= "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n";
        $message .= "💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n";
        $message .= "⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات";
        
        sendTelegramInlineKeyboard($chatId, $message, $sizeButtons);
        return;
    }

    // تخطي اختيار المقاسات
    if ($data === "skip_sizes") {
        writeLog("Skip sizes callback received");
        
        $usersData[$userId]["step"] = "images";
        saveData($usersData, $dataFile);
        
        sendTelegramMessage($chatId, "⏭️ تم تخطي اختيار المقاسات.");
        sendTelegramMessage($chatId, "📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
        
        // إعادة عرض لوحة المفاتيح الرئيسية
        $mainKeyboard = [
            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
            ['❌ إلغاء المنتج']
        ];
        
        $replyMarkup = [
            'keyboard' => $mainKeyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
        writeLog("Skipped sizes selection, moved to images step");
        return;
    }

    // البحث السريع في المقاسات
    // تم حذف هذا القسم بناءً على طلب المستخدم

}

    // استقبال الرسائل النصية
    if (isset($update["message"])) {
        $chatId = $update["message"]["chat"]["id"];
        // استخدام مفتاح نصي ثابت لضمان تخزين البيانات في نفس المفتاح دائماً (حل مشكلة عدم التخزين)
        $userId = (string) $update["message"]["from"]["id"];
        
        if (!isset($usersData[$userId])) {
            $usersData[$userId] = [
                "product" => [],
                "images" => [],
                "step" => null,
                "edit_mode" => null,
                "attributes" => [],
                "tags" => [],
                "description" => "",
                "brand" => "",
                "color_quantities" => []
            ];
        }

        // التحقق من التصريح تم في بداية الملف

    if (isset($update["message"]["text"])) {
        $text = trim($update["message"]["text"]);
        
        switch (strtolower($text)) {
            case "/start":
                sendTelegramMessage($chatId, "مرحباً بك في بوت إضافة المنتجات إلى متجرك!\n\nأرسل /new للبدء بإضافة منتج جديد.\nأرسل /updatecolors لتحديث الألوان الموجودة.\nأرسل /forcecolor لإجبار الألوان لتكون متغيرات ملونة.\n\n⏭️ يمكنك تخطي اختيار المقاسات إذا لم تردها!\n🏷️ العلامات تقبل أي نوع من الفواصل (، , ; ؛)\n📊 يمكنك تحديد الكمية لكل لون - ستظهر في المتغيرات!\n\n🔒 البوت محمي بكلمة مرور للأمان\n📋 الأوامر المتاحة:\n• /new - إضافة منتج جديد\n• /show - عرض البيانات الحالية\n• /logout - تسجيل الخروج\n• /users - عرض المستخدمين المصرح لهم\n• /clearusers - مسح جميع المستخدمين");
                break;

            case "/logout":
                // إزالة المستخدم من المستخدمين المصرح لهم
                unset($authorizedUsers[$userId]);
                saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
                unset($usersData[$userId]);
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "🔒 تم تسجيل الخروج بنجاح.\n\nلإعادة الدخول، أرسل كلمة المرور مرة أخرى.");
                return;

            case "/users":
                // عرض المستخدمين المصرح لهم
                if (empty($authorizedUsers)) {
                    sendTelegramMessage($chatId, "📋 لا يوجد مستخدمون مصرح لهم حالياً.");
                } else {
                    $usersList = "📋 المستخدمون المصرح لهم:\n\n";
                    foreach ($authorizedUsers as $id => $userData) {
                        $username = $userData['username'] ?? 'Unknown';
                        $authorizedAt = $userData['authorized_at'] ?? 'Unknown';
                        $usersList .= "👤 المعرف: $id\n";
                        $usersList .= "📝 اسم المستخدم: $username\n";
                        $usersList .= "📅 تم التفعيل: $authorizedAt\n";
                        $usersList .= "━━━━━━━━━━━━━━━━━━━━\n";
                    }
                    sendTelegramMessage($chatId, $usersList);
                }
                break;

            case "/clearusers":
                // مسح جميع المستخدمين المصرح لهم
                $count = count($authorizedUsers);
                $authorizedUsers = [];
                saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
                sendTelegramMessage($chatId, "🗑️ تم مسح جميع المستخدمين المصرح لهم ($count مستخدم).\n\n⚠️ جميع المستخدمين بحاجة لإدخال كلمة المرور مرة أخرى.");
                break;

            case "/updatecolors":
                sendTelegramMessage($chatId, "⏳ جاري تحديث نوع خاصية الألوان لتكون image...");
                $result = updateExistingColorsToColorType();
                if ($result) {
                    sendTelegramMessage($chatId, "✅ تم تحديث نوع الألوان بنجاح! الآن ستظهر كصور ملونة في الموقع.");
                } else {
                    sendTelegramMessage($chatId, "❌ حدث خطأ أثناء تحديث الألوان.");
                }
                break;

                            case "/forcecolor":
                    sendTelegramMessage($chatId, "⏳ جاري إعداد الألوان لتكون متغيرات ملونة...");
                    $result = forceColorsAsVariations();
                    if ($result) {
                        sendTelegramMessage($chatId, "✅ تم إعداد الألوان بنجاح! الآن ستظهر كصور ملونة في الموقع.");
                    } else {
                        sendTelegramMessage($chatId, "❌ حدث خطأ أثناء إعداد الألوان.");
                    }
                    break;





            case "/cleanimages":
                if (isset($usersData[$userId]["images"]) && !empty($usersData[$userId]["images"])) {
                    $originalCount = count($usersData[$userId]["images"]);
                    $cleaned = cleanDuplicateImages($usersData[$userId]);
                    saveData($usersData, $dataFile);
                    
                    if ($cleaned) {
                        $newCount = count($usersData[$userId]["images"]);
                        $removedCount = $originalCount - $newCount;
                        sendTelegramMessage($chatId, "✅ تم تنظيف الصور المكررة بنجاح!\n🗑️ تم حذف " . $removedCount . " صورة مكررة.\n📸 عدد الصور الحالي: " . $newCount);
                    } else {
                        sendTelegramMessage($chatId, "ℹ️ لا توجد صور مكررة للتنظيف.");
                    }
                } else {
                    sendTelegramMessage($chatId, "⚠️ لا توجد صور لتنظيفها.");
                }
                break;

            case "/new":
                // إعادة تعيين بيانات المنتج
                $usersData[$userId]["product"] = [];
                $usersData[$userId]["images"] = [];
                $usersData[$userId]["step"] = "name";
                $usersData[$userId]["edit_mode"] = null;
                $usersData[$userId]["attributes"] = [];
                $usersData[$userId]["tags"] = [];
                $usersData[$userId]["description"] = "";
                $usersData[$userId]["brand"] = "";
                $usersData[$userId]["color_quantities"] = [];
                
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم بدء منتج جديد!\n📝 الرجاء إدخال اسم المنتج:");
                break;

            default:
        $currentStep = $usersData[$userId]["step"] ?? null;
        writeLog("Message default branch - userId: $userId, step: " . ($currentStep ?? 'null') . ", text length: " . strlen($text));
        
        if ($currentStep === null) {
            sendTelegramMessage($chatId, "⚠️ لبدء إضافة منتج، أرسل أولاً /new ثم أدخل البيانات بالترتيب (الاسم، السعر، SKU، الوصف، العلامات).");
            return;
        }
        
        if ($currentStep === "name") {
            $usersData[$userId]["product"]["name"] = $text;
            $usersData[$userId]["step"] = "price";
            writeLog("Before save - userId: $userId, name: $text, product data: " . json_encode($usersData[$userId]["product"]));
            saveData($usersData, $dataFile);
            writeLog("After save - Saved product name for user $userId: " . $text);
            // التحقق من الحفظ
            $verifyData = loadDataWithLock($dataFile);
            if (isset($verifyData[$userId])) {
                writeLog("Verification - Name saved correctly: " . ($verifyData[$userId]["product"]["name"] ?? "NOT FOUND"));
            } else {
                writeLog("ERROR - User data not found after save! userId: $userId");
            }
            sendTelegramMessage($chatId, "✅ تم حفظ الاسم: $text\n💰 الرجاء إدخال السعر:");
            return;
        }
                
        if ($currentStep === "price") {
            if (!is_numeric($text) || $text <= 0) {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال سعر صالح:");
                return;
            }
            $usersData[$userId]["product"]["price"] = $text;
            $usersData[$userId]["step"] = "sku";
            writeLog("Before save - userId: $userId, price: $text, product data: " . json_encode($usersData[$userId]["product"]));
            saveData($usersData, $dataFile);
            writeLog("After save - Saved product price for user $userId: " . $text);
            // التحقق من الحفظ
            $verifyData = loadDataWithLock($dataFile);
            if (isset($verifyData[$userId])) {
                writeLog("Verification - Price saved correctly: " . ($verifyData[$userId]["product"]["price"] ?? "NOT FOUND"));
            } else {
                writeLog("ERROR - User data not found after save! userId: $userId");
            }
            sendTelegramMessage($chatId, "✅ تم حفظ السعر: $text\n🏷️ الرجاء إدخال رمز المنتج (SKU) - يمكن أن يكون أرقاماً أو أحرفاً:");
            return;
        }

        if ($currentStep === "sku") {
            $usersData[$userId]["product"]["sku"] = trim($text);
            $usersData[$userId]["step"] = "description";
            saveData($usersData, $dataFile);
            writeLog("Saved product sku for user $userId: " . trim($text));
            sendTelegramMessage($chatId, "✅ تم حفظ رمز المنتج (SKU): " . trim($text) . "\n📝 الرجاء إدخال وصف المنتج:");
            return;
        }
                
        if ($currentStep === "description") {
            $usersData[$userId]["description"] = $text;
            $usersData[$userId]["step"] = "tags";
            writeLog("Before save - userId: $userId, description length: " . strlen($text));
            saveData($usersData, $dataFile);
            writeLog("After save - Saved description for user $userId, length: " . strlen($text));
            // التحقق من الحفظ
            $verifyData = loadDataWithLock($dataFile);
            if (isset($verifyData[$userId])) {
                writeLog("Verification - Description saved correctly, length: " . strlen($verifyData[$userId]["description"] ?? ""));
            } else {
                writeLog("ERROR - User data not found after save! userId: $userId");
            }
            sendTelegramMessage($chatId, "✅ تم حفظ الوصف\n🏷️ الرجاء إدخال العلامات (مفصولة بفواصل أو مسافات):\nمثال: علامة1، علامة2، علامة3");
            return;
        }
                
        if ($usersData[$userId]["step"] === "tags") {
            // تقسيم العلامات باستخدام عدة أنواع من الفواصل
            $tags = preg_split('/[,،;؛\s]+/', $text);
            $tags = array_map('trim', $tags);
            $tags = array_filter($tags); // إزالة القيم الفارغة
            
            $usersData[$userId]["tags"] = $tags;
            $usersData[$userId]["step"] = "choose_category";
            saveData($usersData, $dataFile);
            
            sendTelegramMessage($chatId, "✅ تم حفظ العلامات: " . implode("، ", $usersData[$userId]["tags"]));
                    
            // عرض أزرار التصنيفات
            $categories = getAllCategories();
            $catButtons = [];
            $tempButtons = [];
            foreach ($categories as $cat) {
                $tempButtons[] = ["text" => $cat['name'], "callback_data" => 'cat_' . $cat['id']];
                if (count($tempButtons) == 2) {
                    $catButtons[] = $tempButtons;
                    $tempButtons = [];
                }
            }
            if (!empty($tempButtons)) {
                $catButtons[] = $tempButtons;
            }
            sendTelegramInlineKeyboard($chatId, "🗂️ اختر التصنيف:", $catButtons);
            return;
        }

        // معالجة إدخال الكمية
        if ($usersData[$userId]["step"] === "quantity_input") {
            if (!is_numeric($text) || $text < 0) {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم صحيح موجب أو 0 للكمية:");
                return;
            }
            
            $colorName = $usersData[$userId]["current_color"];
            $quantity = intval($text);
            
            // تسجيل قبل التحديث
            writeLog("Before update - Color: '$colorName', Quantity: $quantity");
            writeLog("Before update - All color quantities: " . json_encode($usersData[$userId]["color_quantities"]));
            
            // التأكد من أن مصفوفة الكميات موجودة
            if (!isset($usersData[$userId]["color_quantities"])) {
                $usersData[$userId]["color_quantities"] = [];
            }
            
            $usersData[$userId]["color_quantities"][$colorName] = $quantity;
            writeLog("Set quantity for color '$colorName': $quantity");
            writeLog("Updated color quantities: " . json_encode($usersData[$userId]["color_quantities"]));
            
            // وضع الحلقة لتحديد الكميات لكل الألوان
            if (!empty($usersData[$userId]["quantity_loop"])) {
                $index = isset($usersData[$userId]["quantity_loop_index"]) ? intval($usersData[$userId]["quantity_loop_index"]) : 0;
                $index++;
                $colorsList = $usersData[$userId]["attributes"]["color"];
                if ($index < count($colorsList)) {
                    $nextColor = $colorsList[$index];
                    $usersData[$userId]["quantity_loop_index"] = $index;
                    $usersData[$userId]["current_color"] = $nextColor;
                    $usersData[$userId]["step"] = "quantity_input";
                    saveData($usersData, $dataFile);
                    $currentQuantityNext = isset($usersData[$userId]["color_quantities"][$nextColor]) ? $usersData[$userId]["color_quantities"][$nextColor] : 0;
                    sendTelegramMessage($chatId, "✅ تم تحديد كمية اللون '$colorName': $quantity قطعة\n\n📊 الرجاء إدخال الكمية للون '$nextColor':\n💡 الكمية الحالية: $currentQuantityNext\n💡 أدخل رقم صحيح (مثال: 5) أو 0 إذا لم ترد بيع هذا اللون");
                    return;
                }
                // انتهت الحلقة
                unset($usersData[$userId]["quantity_loop"]);
                unset($usersData[$userId]["quantity_loop_index"]);
                unset($usersData[$userId]["current_color"]);
                
                // الانتقال للمقاسات بعد إدخال جميع الكميات
                $usersData[$userId]["step"] = "choose_size";
                saveData($usersData, $dataFile);
                
                // جلب المقاسات
                $sizes = getAttributeTerms('pa_size');
                if (empty($sizes)) {
                    $usersData[$userId]["step"] = "images";
                    saveData($usersData, $dataFile);
                    sendTelegramMessage($chatId, "✅ تم تحديد جميع الكميات.\n📸 لم يتم العثور على مقاسات. يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
                    
                    $mainKeyboard = [
                        ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                        ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                        ['❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $mainKeyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                    return;
                }
                
                $sizeButtons = createSizeButtons($sizes, [], 0);
                sendTelegramInlineKeyboard($chatId, "✅ تم تحديد جميع الكميات.\n\n📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات", $sizeButtons);
                return;
            }
            
            // الوضع الافتراضي (تحرير يدوي للكميات)
            $usersData[$userId]["step"] = "set_quantities";
            unset($usersData[$userId]["current_color"]);
            saveData($usersData, $dataFile);
            
            // التحقق من الحفظ
            $savedData = json_decode(file_get_contents($dataFile), true);
            writeLog("Saved data verification - Color quantities: " . json_encode($savedData[$userId]["color_quantities"] ?? []));
            
            // التحقق من أن الكمية تم حفظها بشكل صحيح
            if (isset($savedData[$userId]["color_quantities"][$colorName])) {
                writeLog("✅ Quantity saved successfully for color '$colorName': " . $savedData[$userId]["color_quantities"][$colorName]);
            } else {
                writeLog("❌ FAILED to save quantity for color '$colorName'");
            }
            sendTelegramMessage($chatId, "✅ تم تحديد كمية اللون '$colorName': $quantity قطعة");
            
            // إعادة عرض أزرار الكميات
            $quantityButtons = [];
            foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                $currentQuantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
                $quantityButtons[] = [["text" => "🎨 $color: $currentQuantity قطعة", "callback_data" => "quantity_color_" . $color]];
            }
            
            $quantityButtons[] = [["text" => "✅ تم تحديد الكميات", "callback_data" => "done_quantities"]];
            $quantityButtons[] = [["text" => "⬅️ رجوع", "callback_data" => "back_to_colors"]];
            
            sendTelegramInlineKeyboard($chatId, "�� حدد الكمية لكل لون:\n💡 اضغط على اللون لتغيير كميته", $quantityButtons);
            return;
        }

                if ($usersData[$userId]["step"] === "choose_color") {
                    // جلب الألوان من WooCommerce
                    $colors = getAttributeTerms('pa_color');
                    $colorButtons = [];
                    $tempButtons = [];
                    
                    foreach ($colors as $color) {
                        $tempButtons[] = ["text" => $color['name'], "callback_data" => 'color_' . $color['name']];
                        if (count($tempButtons) == 2) {
                            $colorButtons[] = $tempButtons;
                            $tempButtons = [];
                        }
                    }
                    
                    if (!empty($tempButtons)) {
                        $colorButtons[] = $tempButtons;
                    }
                    
                    if (empty($colorButtons)) {
                        // إذا لم تكن هناك ألوان، انتقل مباشرة إلى المقاسات
                    $usersData[$userId]["step"] = "choose_size";
                saveData($usersData, $dataFile);
                        sendTelegramMessage($chatId, "⚠️ لم يتم العثور على ألوان في الموقع.");
                    
                    // جلب المقاسات من WooCommerce
                    $sizes = getAttributeTerms('pa_size');
                    $sizeButtons = [];
                    $tempButtons = [];
                    
                    foreach ($sizes as $size) {
                        $tempButtons[] = ["text" => $size['name'], "callback_data" => 'size_' . $size['name']];
                        if (count($tempButtons) == 2) {
                            $sizeButtons[] = $tempButtons;
                            $tempButtons = [];
                        }
                    }
                    
                    if (!empty($tempButtons)) {
                        $sizeButtons[] = $tempButtons;
                    }
                    
                    if (empty($sizeButtons)) {
                        // إذا لم تكن هناك مقاسات، انتقل مباشرة إلى الصور
                        $usersData[$userId]["step"] = "images";
                saveData($usersData, $dataFile);
                        sendTelegramMessage($chatId, "📸 لم يتم العثور على مقاسات. يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
                        
                        // إعادة عرض لوحة المفاتيح الرئيسية
                        $mainKeyboard = [
                            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                            ['❌ إلغاء المنتج']
                        ];
                        
                        $replyMarkup = [
                            'keyboard' => $mainKeyboard,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false
                        ];
                        
                        sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                        writeLog("No sizes found, moved to images step");
                        return;
                    }
                    
                    $sizeButtons[] = [["text" => "✅ تم اختيار المقاسات", "callback_data" => "done_sizes"]];
                    
                    sendTelegramInlineKeyboard($chatId, "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n�� اضغط على المقاس مرة أخرى لإزالته من الاختيار", $sizeButtons);
                    writeLog("Sent size selection keyboard");
                    return;
                }

                    $colorButtons[] = [["text" => "✅ تم اختيار الألوان", "callback_data" => "done_colors"]];
                    $colorButtons[] = [["text" => "📊 تحديد الكميات", "callback_data" => "set_quantities"]];
                    
                    sendTelegramInlineKeyboard($chatId, "🎨 اختر الألوان المطلوبة (يمكنك اختيار أكثر من لون):\n💡 اضغط على اللون مرة أخرى لإزالته من الاختيار\n📊 يمكنك تحديد الكمية لكل لون بعد اختيار الألوان", $colorButtons);
                        return;
                    }
                    
                if ($usersData[$userId]["step"] === "choose_size") {
                    // جلب المقاسات من WooCommerce
                    $sizes = getAttributeTerms('pa_size');
                    
                    if (empty($sizes)) {
                        // إذا لم تكن هناك مقاسات، انتقل مباشرة إلى الصور
                        $usersData[$userId]["step"] = "images";
                        saveData($usersData, $dataFile);
                        sendTelegramMessage($chatId, "📸 لم يتم العثور على مقاسات. يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n💡 جميع الصور ستظهر في معرض المنتج الرئيسي.");
                        
                        // إعادة عرض لوحة المفاتيح الرئيسية
                        $mainKeyboard = [
                            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                            ['❌ إلغاء المنتج']
                        ];
                        
                        $replyMarkup = [
                            'keyboard' => $mainKeyboard,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false
                        ];
                        
                        sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                        return;
                    }
                    
                    $sizeButtons = createSizeButtons($sizes, [], 0);
                    sendTelegramInlineKeyboard($chatId, "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار", $sizeButtons);
                    return;
                }
        }
    }

    // معالجة الصور
    if (isset($update["message"]["photo"])) {
        $chatId = $update["message"]["chat"]["id"];
        $userId = (string) $update["message"]["from"]["id"];
        
        if (!isset($usersData[$userId])) {
            $usersData[$userId] = [
                "product" => [],
                "images" => [],
                "step" => null,
                "edit_mode" => null,
                "attributes" => [],
                "tags" => [],
                "description" => "",
                "brand" => "",
                "color_quantities" => [] // إضافة مصفوفة لتخزين كميات الألوان
            ];
        }

        // التحقق من التصريح تم في بداية الملف

        $photos = $update["message"]["photo"];
        $fileId = end($photos)["file_id"];

        // تم إزالة معالجة ربط الألوان بالصور - الانتقال مباشرة للمقاسات

        // معالجة الصور العادية - فقط عندما تكون في خطوة الصور
        if ($usersData[$userId]["step"] !== "images") {
            $step = $usersData[$userId]["step"] ?? 'لا يوجد';
            writeLog("Photo received but step is not 'images'. Current step: " . $step);
            sendTelegramMessage($chatId, "⚠️ لا يمكن إضافة الصور الآن.\n\nيجب إكمال بيانات المنتج أولاً:\n📝 الاسم → 💰 السعر → 🏷️ SKU → 📄 الوصف → 🏷️ العلامات → 📁 التصنيف → 👔 الماركة → الألوان والمقاسات.\n\nأرسل /new ثم اتبع الخطوات بالترتيب. الصور تُضاف في النهاية فقط.");
            return;
        }

        if (!isset($usersData[$userId]["images"])) {
            $usersData[$userId]["images"] = [];
        }

        // منع تكرار نفس الصورة
        if (in_array($fileId, $usersData[$userId]["images"])) {
            sendTelegramMessage($chatId, "⚠️ هذه الصورة موجودة مسبقاً. تم تجاهلها.");
            return;
        }

        $usersData[$userId]["images"][] = $fileId;
        saveData($usersData, $dataFile);

        $imageCount = count($usersData[$userId]["images"]);

        // إرسال رسالة تأكيد بسيطة أولاً
        $url = "https://api.telegram.org/bot$telegramToken/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => "✅ تم استلام الصورة رقم $imageCount"
        ];
        
        file_get_contents($url . '?' . http_build_query($data));

        // ثم إرسال لوحة المفاتيح
        $keyboard = [
            ['📋 عرض البيانات', '📤 رفع المنتج'],
            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
            ['❌ إلغاء المنتج']
        ];

        $url = "https://api.telegram.org/bot$telegramToken/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => "يمكنك إرسال المزيد من الصور أو اختيار أحد الخيارات التالية:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ])
        ];

        file_get_contents($url . '?' . http_build_query($data));
        return;
    }

    // في قسم معالجة الرسائل النصية
    if (isset($update["message"]["text"])) {
        $text = trim($update["message"]["text"]);
        
        switch (strtolower($text)) {
            case "/show":
                try {
                    $prod = $usersData[$userId]["product"] ?? [];
                    $msg = "📦 بيانات المنتج الحالية:\n\n";
                    
                    // الاسم
                    $msg .= "📝 الاسم: " . ($prod["name"] ?? "غير محدد") . "\n";
                    
                    // السعر
                    $msg .= "💰 السعر: " . ($prod["price"] ?? "غير محدد") . "\n";
                    
                    // رمز المنتج SKU
                    $msg .= "🏷️ رمز المنتج (SKU): " . (isset($prod["sku"]) && trim($prod["sku"]) !== '' ? $prod["sku"] : "غير محدد") . "\n";
                    
                    // الوصف
                    $msg .= "📄 الوصف: " . ($usersData[$userId]["description"] ?? "غير محدد") . "\n";
                    
                    // العلامات
                    if (!empty($usersData[$userId]["tags"])) {
                        $msg .= "🏷️ العلامات: " . implode("، ", $usersData[$userId]["tags"]) . "\n";
                            } else {
                        $msg .= "🏷️ العلامات: غير محددة\n";
                    }
                    
                    // التصنيف
                    $msg .= "📁 التصنيف: " . ($prod["category"] ?? "غير محدد") . "\n";
                    
                    // الماركة
                    $msg .= "👔 الماركة: " . ($usersData[$userId]["brand"] ?? "غير محددة") . "\n";
                    
                    // الألوان
                    if (!empty($usersData[$userId]["attributes"]["color"])) {
                        $colorArray = [];
                        foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                            $quantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
                            $colorArray[] = $color . " ($quantity قطعة)";
                        }
                        $msg .= "🎨 الألوان: " . implode("، ", $colorArray) . "\n";
                    } else {
                        $msg .= "🎨 الألوان: غير محددة\n";
                    }

                    // المقاسات
                    if (!empty($usersData[$userId]["attributes"]["size"])) {
                        $msg .= "📏 المقاسات: " . implode("، ", $usersData[$userId]["attributes"]["size"]) . "\n";
                    } else {
                        $msg .= "📏 المقاسات: غير محددة\n";
                    }

                    // عدد الصور
                    $imageCount = isset($usersData[$userId]["images"]) ? count($usersData[$userId]["images"]) : 0;
                    $uniqueImageCount = isset($usersData[$userId]["images"]) ? count(array_unique($usersData[$userId]["images"])) : 0;
                    $msg .= "📸 عدد الصور: " . $imageCount . " (فريدة: " . $uniqueImageCount . ")\n";
                    $msg .= "💡 جميع الصور ستظهر في معرض المنتج الرئيسي\n";
                    
                    $msg .= "\n";
                    
                    // إضافة أزرار التحكم
                    $keyboard = [
                        ['📤 رفع المنتج', '✏️ تعديل البيانات'],
                        ['🗑️ حذف آخر صورة', '❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $keyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, $msg, $replyMarkup);
                } catch (Exception $e) {
                    writeLog("Error in show command: " . $e->getMessage());
                    sendTelegramMessage($chatId, "⚠️ حدث خطأ أثناء عرض البيانات. الرجاء المحاولة مرة أخرى.");
                }
                break;

            case "📤 رفع المنتج":
            case "رفع المنتج":
            case "/upload":
                writeLog("Upload request received from user: " . $userId);
                
                // دائماً قراءة البيانات من الملف مع قفل لضمان الحصول على آخر حالة محفوظة (حل: مرة يرفع ومرة لا)
                $usersDataFromFile = loadDataWithLock($dataFile);
                $userKey = (string)$userId;
                
                // محاولة قراءة البيانات بجميع الطرق الممكنة
                $ud = null;
                if (isset($usersDataFromFile[$userKey])) {
                    $ud = $usersDataFromFile[$userKey];
                    writeLog("Found user data using string key: $userKey");
                } elseif (isset($usersDataFromFile[$userId])) {
                    $ud = $usersDataFromFile[$userId];
                    writeLog("Found user data using original userId: $userId");
                } elseif (isset($usersData[$userKey])) {
                    $ud = $usersData[$userKey];
                    writeLog("Found user data in memory using string key: $userKey");
                } elseif (isset($usersData[$userId])) {
                    $ud = $usersData[$userId];
                    writeLog("Found user data in memory using original userId: $userId");
                }
                
                if (!$ud || !is_array($ud)) {
                    $ud = [];
                    writeLog("WARNING: No user data found! userId: $userId, userKey: $userKey");
                    writeLog("Available keys in file: " . json_encode(array_keys($usersDataFromFile)));
                    writeLog("Available keys in memory: " . json_encode(array_keys($usersData)));
                }
                
                $prod = $ud["product"] ?? [];
                
                // تسجيل تفصيلي لجميع البيانات
                writeLog("=== DETAILED DATA CHECK ===");
                writeLog("User ID: $userId (type: " . gettype($userId) . ")");
                writeLog("User Key: $userKey (type: " . gettype($userKey) . ")");
                writeLog("Product data: " . json_encode($prod));
                writeLog("Description: " . ($ud["description"] ?? "NOT SET"));
                writeLog("Brand: " . ($ud["brand"] ?? "NOT SET"));
                writeLog("Category: " . ($prod["category"] ?? "NOT SET"));
                writeLog("Images count: " . (isset($ud["images"]) ? count($ud["images"]) : 0));
                
                // تحسين التحقق من البيانات
                $hasName = !empty($prod["name"]) && trim((string)$prod["name"]) !== '';
                $hasPrice = !empty($prod["price"]) && is_numeric($prod["price"]) && $prod["price"] > 0;
                $hasCategory = !empty($prod["category"]) && trim((string)$prod["category"]) !== '';
                $hasDescription = !empty($ud["description"]) && trim((string)$ud["description"]) !== '';
                $hasBrand = !empty($ud["brand"]) && trim((string)$ud["brand"]) !== '';
                $hasAll = $hasName && $hasPrice && $hasCategory && $hasDescription && $hasBrand;
                
                writeLog("Validation results - Name: " . ($hasName ? 'YES' : 'NO') . ", Price: " . ($hasPrice ? 'YES' : 'NO') . ", Category: " . ($hasCategory ? 'YES' : 'NO') . ", Description: " . ($hasDescription ? 'YES' : 'NO') . ", Brand: " . ($hasBrand ? 'YES' : 'NO'));
                writeLog("=== END DATA CHECK ===");
                
                if (empty($ud["images"])) {
                    sendTelegramMessage($chatId, "⚠️ الرجاء إضافة صورة واحدة على الأقل قبل رفع المنتج.");
                    return;
                }
                
                if ($hasAll) {
                    
                    writeLog("Starting direct product upload for user: " . $userId);
                    sendTelegramMessage($chatId, "⏳ جاري رفع المنتج...");
                    
                    try {
                        // رفع المنتج مباشرة (باستخدام البيانات المُعاد تحميلها من الملف)
                        $result = uploadProduct($ud, $chatId);
                        writeLog("Upload result: " . ($result ? "success" : "failed"));
                        
                        if ($result === true) {
                            $imageCount = count($ud["images"]);
                            sendTelegramMessage($chatId, "✅ تم رفع المنتج بنجاح!\n📸 تم رفع " . $imageCount . " صور في معرض المنتج الرئيسي\n💡 جميع الصور ستظهر في المعرض بدون تكرار\n\nيمكنك إضافة منتج جديد باستخدام /new");
                            $usersData = loadDataWithLock($dataFile);
                            unset($usersData[$userKey]);
                            unset($usersData[$userId]);
                            saveData($usersData, $dataFile);
                            writeLog("Product uploaded successfully and user data cleared");
                            
                            // عرض زر إضافة منتج جديد
                            $keyboard = [
                                ['📦 إضافة منتج جديد']
                            ];
                            
                            $replyMarkup = [
                                'keyboard' => $keyboard,
                                'resize_keyboard' => true,
                                'one_time_keyboard' => true
                            ];
                            
                            sendTelegramKeyboard($chatId, "هل تريد إضافة منتج جديد؟", $replyMarkup);
                } else {
                            sendTelegramMessage($chatId, "❌ حدث خطأ أثناء رفع المنتج. الرجاء المحاولة مرة أخرى.");
                            
                            // عرض أزرار التحكم مرة أخرى
                            $keyboard = [
                                ['📤 رفع المنتج', '✏️ تعديل البيانات'],
                                ['🗑️ حذف آخر صورة', '❌ إلغاء المنتج']
                            ];
                            
                            $replyMarkup = [
                                'keyboard' => $keyboard,
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false
                            ];
                            
                            sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                        }
                    } catch (Exception $e) {
                        writeLog("Error during upload: " . $e->getMessage());
                        sendTelegramMessage($chatId, "❌ حدث خطأ أثناء رفع المنتج: " . $e->getMessage());
                    }
                } else {
                    // توضيح بالضبط ما الناقص
                    $missing = [];
                    if (!$hasName) $missing[] = "📝 الاسم";
                    if (!$hasPrice) $missing[] = "💰 السعر";
                    if (!$hasCategory) $missing[] = "📁 التصنيف";
                    if (!$hasDescription) $missing[] = "📄 الوصف";
                    if (!$hasBrand) $missing[] = "👔 الماركة";
                    
                    // إضافة معلومات تشخيصية
                    $debugInfo = "\n\n🔍 معلومات تشخيصية:\n";
                    $debugInfo .= "الاسم: " . ($prod["name"] ?? "غير موجود") . "\n";
                    $debugInfo .= "السعر: " . ($prod["price"] ?? "غير موجود") . "\n";
                    $debugInfo .= "التصنيف: " . ($prod["category"] ?? "غير موجود") . "\n";
                    $debugInfo .= "الوصف: " . (empty($ud["description"]) ? "غير موجود" : "موجود (" . strlen($ud["description"] ?? "") . " حرف)") . "\n";
                    $debugInfo .= "الماركة: " . ($ud["brand"] ?? "غير موجود");
                    
                    $msg = "⚠️ لا يمكن الرفع - البيانات الناقصة:\n\n" . implode("\n", $missing);
                    $msg .= $debugInfo;
                    $msg .= "\n\n💡 الطريقة الصحيحة: أرسل /new ثم أدخل بالترتيب:\n1️⃣ اسم المنتج\n2️⃣ السعر\n3️⃣ رمز SKU\n4️⃣ الوصف\n5️⃣ العلامات\n6️⃣ اختر التصنيف والماركة والألوان والمقاسات\n7️⃣ بعدها أضف الصور واضغط رفع المنتج";
                    sendTelegramMessage($chatId, $msg);
                }
                break;

            case "📦 إضافة منتج جديد":
            case "/new":
                // إعادة تعيين بيانات المنتج
                $usersData[$userId]["product"] = [];
                $usersData[$userId]["images"] = [];
                $usersData[$userId]["step"] = "name";
                $usersData[$userId]["edit_mode"] = null;
                $usersData[$userId]["attributes"] = [];
                $usersData[$userId]["tags"] = [];
                $usersData[$userId]["description"] = "";
                $usersData[$userId]["brand"] = "";
                $usersData[$userId]["color_quantities"] = [];
                
                saveData($usersData, $dataFile);
                
                // إظهار لوحة المفاتيح الرئيسية
                $keyboard = [
                    ['📤 رفع المنتج', '✏️ تعديل البيانات'],
                    ['🗑️ حذف آخر صورة', '❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "📝 الرجاء إدخال اسم المنتج:", $replyMarkup);
                break;

            case "✏️ تعديل البيانات":
                $editKeyboard = [
                    ['تعديل الاسم', 'تعديل السعر'],
                    ['تعديل SKU', 'تعديل الوصف'],
                    ['تعديل العلامات', 'تعديل الألوان'],
                    ['تعديل المقاسات', 'تعديل الكميات'],
                    ['رجوع']
                ];
                
                $replyMarkup = [
                    'keyboard' => $editKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "اختر ما تريد تعديله:", $replyMarkup);
                break;

            case "🗑️ حذف آخر صورة":
            case "/deleteimage":
                if (!empty($usersData[$userId]["images"])) {
                    array_pop($usersData[$userId]["images"]);
                    saveData($usersData, $dataFile);
                    $remainingImages = count($usersData[$userId]["images"]);
                    sendTelegramMessage($chatId, "🗑️ تم حذف آخر صورة. عدد الصور المتبقية: " . $remainingImages);
                } else {
                    sendTelegramMessage($chatId, "⚠️ لا توجد صور لحذفها.");
                }
                break;

            case "🧹 تنظيف الصور المكررة":
                if (isset($usersData[$userId]["images"]) && !empty($usersData[$userId]["images"])) {
                    $originalCount = count($usersData[$userId]["images"]);
                    $cleaned = cleanDuplicateImages($usersData[$userId]);
                    saveData($usersData, $dataFile);
                    
                    if ($cleaned) {
                        $newCount = count($usersData[$userId]["images"]);
                        $removedCount = $originalCount - $newCount;
                        sendTelegramMessage($chatId, "✅ تم تنظيف الصور المكررة بنجاح!\n🗑️ تم حذف " . $removedCount . " صورة مكررة.\n📸 عدد الصور الحالي: " . $newCount);
                    } else {
                        sendTelegramMessage($chatId, "ℹ️ لا توجد صور مكررة للتنظيف.");
                    }
                } else {
                    sendTelegramMessage($chatId, "⚠️ لا توجد صور لتنظيفها.");
                }
                break;

            case "📋 عرض البيانات الحالية":
            case "📋 عرض البيانات":
            case "/show":
                try {
                    if (!isset($usersData[$userId])) {
                        sendTelegramMessage($chatId, "⚠️ لا توجد بيانات منتج لعرضها. الرجاء البدء بإضافة منتج جديد باستخدام /new");
                        return;
                    }
                    
                    // التحقق من وجود بيانات المنتج
                    if (empty($usersData[$userId]["product"]) && empty($usersData[$userId]["description"]) && empty($usersData[$userId]["brand"])) {
                        sendTelegramMessage($chatId, "⚠️ لا توجد بيانات منتج لعرضها. الرجاء البدء بإضافة منتج جديد باستخدام /new");
                        return;
                    }
                    
                    $prod = $usersData[$userId]["product"] ?? [];
                    $msg = "📦 بيانات المنتج الحالية:\n\n";
                    
                    // الاسم
                    $msg .= "📝 الاسم: " . ($prod["name"] ?? "غير محدد") . "\n";
                    
                    // السعر
                    $msg .= "💰 السعر: " . ($prod["price"] ?? "غير محدد") . "\n";
                    
                    // رمز المنتج SKU
                    $msg .= "🏷️ رمز المنتج (SKU): " . (isset($prod["sku"]) && trim($prod["sku"]) !== '' ? $prod["sku"] : "غير محدد") . "\n";
                    
                    // الوصف
                    $msg .= "📄 الوصف: " . ($usersData[$userId]["description"] ?? "غير محدد") . "\n";
                    
                    // العلامات
                    if (!empty($usersData[$userId]["tags"])) {
                        $msg .= "🏷️ العلامات: " . implode("، ", $usersData[$userId]["tags"]) . "\n";
                    } else {
                        $msg .= "🏷️ العلامات: غير محددة\n";
                    }
                    
                    // التصنيف
                    $msg .= "📁 التصنيف: " . ($prod["category"] ?? "غير محدد") . "\n";
                    
                    // الماركة
                    $msg .= "👔 الماركة: " . ($usersData[$userId]["brand"] ?? "غير محددة") . "\n";
                    
                    // الألوان
                    if (!empty($usersData[$userId]["attributes"]["color"])) {
                        $colorArray = [];
                        foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                            $quantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
                            $colorArray[] = $color . " ($quantity قطعة)";
                        }
                        $msg .= "🎨 الألوان: " . implode("، ", $colorArray) . "\n";
                    } else {
                        $msg .= "🎨 الألوان: غير محددة\n";
                    }

                    // المقاسات
                    if (!empty($usersData[$userId]["attributes"]["size"])) {
                        $msg .= "📏 المقاسات: " . implode("، ", $usersData[$userId]["attributes"]["size"]) . "\n";
                    } else {
                        $msg .= "📏 المقاسات: غير محددة\n";
                    }

                    // عدد الصور
                    $imageCount = isset($usersData[$userId]["images"]) ? count($usersData[$userId]["images"]) : 0;
                    $uniqueImageCount = isset($usersData[$userId]["images"]) ? count(array_unique($usersData[$userId]["images"])) : 0;
                    $msg .= "📸 عدد الصور: " . $imageCount . " (فريدة: " . $uniqueImageCount . ")\n";
                    $msg .= "💡 جميع الصور ستظهر في معرض المنتج الرئيسي\n\n";
                    
                    // إضافة أزرار التحكم
                    $keyboard = [
                        ['📤 رفع المنتج', '✏️ تعديل البيانات'],
                        ['🗑️ حذف آخر صورة', '❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $keyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, $msg, $replyMarkup);
                } catch (Exception $e) {
                    writeLog("Error in show command: " . $e->getMessage());
                    sendTelegramMessage($chatId, "⚠️ حدث خطأ أثناء عرض البيانات. الرجاء المحاولة مرة أخرى.");
                }
                break;

            case "❌ إلغاء المنتج":
            case "/cancel":
                // إعادة تعيين بيانات المنتج
                $usersData[$userId]["product"] = [];
                $usersData[$userId]["images"] = [];
                $usersData[$userId]["step"] = null;
                $usersData[$userId]["edit_mode"] = null;
                $usersData[$userId]["attributes"] = [];
                $usersData[$userId]["tags"] = [];
                $usersData[$userId]["description"] = "";
                $usersData[$userId]["brand"] = "";
                $usersData[$userId]["color_quantities"] = [];
                
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "❌ تم إلغاء المنتج بالكامل. يمكنك البدء من جديد بإرسال /new");
                break;

            case "⏭️ تخطي هذا اللون":
                // تم إزالة معالجة ربط الألوان بالصور
                sendTelegramMessage($chatId, "⚠️ هذه الوظيفة لم تعد متاحة. تم إزالة ربط الصور بالألوان.");
                break;

            case "🔄 إعادة إرسال":
                // تم إزالة معالجة ربط الألوان بالصور
                sendTelegramMessage($chatId, "⚠️ هذه الوظيفة لم تعد متاحة. تم إزالة ربط الصور بالألوان.");
                break;

            case "❌ إلغاء ربط الصور":
                // تم إزالة معالجة ربط الألوان بالصور
                sendTelegramMessage($chatId, "⚠️ هذه الوظيفة لم تعد متاحة. تم إزالة ربط الصور بالألوان.");
                break;

            case "رجوع":
                // العودة للقائمة الرئيسية
                $usersData[$userId]["edit_mode"] = null;
                $usersData[$userId]["step"] = "images";
                saveData($usersData, $dataFile);
                
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "تم العودة للقائمة الرئيسية. اختر من القائمة:", $replyMarkup);
                        break;

            // معالجة أزرار التعديل
            case "تعديل الاسم":
                $usersData[$userId]["edit_mode"] = "editname";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📝 الرجاء إدخال الاسم الجديد:");
                        break;

            case "تعديل السعر":
                $usersData[$userId]["edit_mode"] = "editprice";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "💰 الرجاء إدخال السعر الجديد:");
                        break;

            case "تعديل SKU":
                $usersData[$userId]["edit_mode"] = "editsku";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "🏷️ الرجاء إدخال رمز المنتج (SKU) الجديد - أرقام أو أحرف:");
                break;

            case "تعديل الوصف":
                $usersData[$userId]["edit_mode"] = "editdescription";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📄 الرجاء إدخال الوصف الجديد:");
                        break;

            case "تعديل العلامات":
                $usersData[$userId]["edit_mode"] = "edittags";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "🏷️ الرجاء إدخال العلامات الجديدة (مفصولة بفواصل أو مسافات):\nمثال: علامة1، علامة2، علامة3");
                        break;

            case "تعديل الألوان":
                $usersData[$userId]["step"] = "choose_color";
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                
                // جلب الألوان من WooCommerce
                $colors = getAttributeTerms('pa_color');
                $colorButtons = [];
                $tempButtons = [];
                
                foreach ($colors as $color) {
                    $tempButtons[] = ["text" => $color['name'], "callback_data" => 'color_' . $color['name']];
                    if (count($tempButtons) == 2) {
                        $colorButtons[] = $tempButtons;
                        $tempButtons = [];
                    }
                }
                
                if (!empty($tempButtons)) {
                    $colorButtons[] = $tempButtons;
                }
                
                if (empty($colorButtons)) {
                sendTelegramMessage($chatId, "🎨 الرجاء إدخال الألوان مفصولة بفواصل، مثال:\nأحمر، أزرق، أسود");
                    return;
                }
                
                $colorButtons[] = [["text" => "✅ تم اختيار الألوان", "callback_data" => "done_colors"]];
                $colorButtons[] = [["text" => "📊 تحديد الكميات", "callback_data" => "set_quantities"]];
                
                sendTelegramInlineKeyboard($chatId, "�� اختر الألوان المطلوبة (يمكنك اختيار أكثر من لون):\n💡 اضغط على اللون مرة أخرى لإزالته من الاختيار\n📊 يمكنك تحديد الكمية لكل لون بعد اختيار الألوان", $colorButtons);
                writeLog("Sent color selection keyboard in edit mode");
                        break;

            case "تعديل المقاسات":
                $usersData[$userId]["step"] = "choose_size";
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                
                // جلب المقاسات من WooCommerce
                $sizes = getAttributeTerms('pa_size');
                
                if (empty($sizes)) {
                    sendTelegramMessage($chatId, "⚠️ لم يتم العثور على مقاسات في الموقع.");
                    
                    // إعادة عرض لوحة المفاتيح الرئيسية
                    $mainKeyboard = [
                        ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                        ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                        ['❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $mainKeyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                    return;
                }
                
                        $sizeButtons = createSizeButtons($sizes, $usersData[$userId]["attributes"]["size"] ?? [], 0);
        sendTelegramInlineKeyboard($chatId, "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار\n⏭️ أو اضغط 'تخطي المقاسات' إذا لم ترد اختيار مقاسات", $sizeButtons);
                writeLog("Sent size selection keyboard in edit mode");
                break;

            case "تعديل الكميات":
                if (empty($usersData[$userId]["attributes"]["color"])) {
                    sendTelegramMessage($chatId, "⚠️ لا توجد ألوان محددة لتعديل كمياتها.");
                    
                    // إعادة عرض لوحة المفاتيح الرئيسية
                    $mainKeyboard = [
                        ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                        ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                        ['❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $mainKeyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                    return;
                }
                
                $usersData[$userId]["step"] = "set_quantities";
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                
                $quantityButtons = [];
                foreach ($usersData[$userId]["attributes"]["color"] as $color) {
                    $currentQuantity = isset($usersData[$userId]["color_quantities"][$color]) ? $usersData[$userId]["color_quantities"][$color] : 0;
                    $quantityButtons[] = [["text" => "🎨 $color: $currentQuantity قطعة", "callback_data" => "quantity_color_" . $color]];
                }
                
                $quantityButtons[] = [["text" => "✅ تم تحديد الكميات", "callback_data" => "done_quantities"]];
                $quantityButtons[] = [["text" => "⬅️ رجوع", "callback_data" => "back_to_colors"]];
                
                sendTelegramInlineKeyboard($chatId, "📊 حدد الكمية لكل لون:\n💡 اضغط على اللون لتغيير كميته", $quantityButtons);
                writeLog("Sent quantity selection keyboard in edit mode");
                break;

            default:
                // معالجة البحث في المقاسات
                if (isset($usersData[$userId]["size_search_mode"]) && $usersData[$userId]["size_search_mode"]) {
                    $usersData[$userId]["size_search_mode"] = false;
                    saveData($usersData, $dataFile);
                    
                    // البحث في المقاسات
                    $sizes = getAttributeTerms('pa_size');
                    $searchResults = [];
                    
                    foreach ($sizes as $size) {
                        if (stripos($size['name'], $text) !== false) {
                            $searchResults[] = $size;
                        }
                    }
                    
                    if (!empty($searchResults)) {
                        $message = "🔍 نتائج البحث عن '$text':\n\n";
                        $message .= "📏 اختر المقاس المطلوب:";
                        
                        $searchButtons = [];
                        $tempButtons = [];
                        foreach ($searchResults as $size) {
                            $isSelected = in_array($size['name'], $usersData[$userId]["attributes"]["size"] ?? []);
                            $buttonText = $size['name'] . ($isSelected ? ' ✓' : '');
                            $tempButtons[] = ["text" => $buttonText, "callback_data" => 'size_' . $size['name']];
                            if (count($tempButtons) == 3) {
                                $searchButtons[] = $tempButtons;
                                $tempButtons = [];
                            }
                        }
                        if (!empty($tempButtons)) {
                            $searchButtons[] = $tempButtons;
                        }
                        
                        // إضافة زر العودة
                        $searchButtons[] = [["text" => "⬅️ العودة للقائمة الكاملة", "callback_data" => "size_back_to_main"]];
                        
                        sendTelegramInlineKeyboard($chatId, $message, $searchButtons);
                    } else {
                        sendTelegramMessage($chatId, "❌ لم يتم العثور على مقاسات تحتوي على '$text'");
                        
                        // إعادة عرض القائمة الرئيسية
                        $sizes = getAttributeTerms('pa_size');
                        $sizeButtons = createSizeButtons($sizes, $usersData[$userId]["attributes"]["size"] ?? [], 0);
                        
                        $selectedSizes = empty($usersData[$userId]["attributes"]["size"]) ? 
                            "لم يتم اختيار أي مقاس بعد" : 
                            "المقاسات المختارة: " . implode("، ", $usersData[$userId]["attributes"]["size"]);
                        
                        $message = $selectedSizes . "\n\n";
                        $message .= "📏 اختر المقاسات المطلوبة (يمكنك اختيار أكثر من مقاس):\n";
                        $message .= "💡 اضغط على المقاس مرة أخرى لإزالته من الاختيار";
                        
                        sendTelegramInlineKeyboard($chatId, $message, $sizeButtons);
                    }
                    return;
                }
                
                // معالجة الإدخالات النصية حسب الخطوة أو وضع التعديل
                handleTextInput($userId, $chatId, $text, $usersData);
                        break;
        }
                                    }


}

function uploadProduct($userData, $chatId) {
    try {
        global $woocommerceUrl, $consumerKey, $consumerSecret;
        writeLog("Starting product upload process...");

        // 0. تنظيف الصور المكررة
        $cleaned = cleanDuplicateImages($userData);
        if ($cleaned) {
            writeLog("Duplicate images cleaned before upload");
        }

        // 1. التحقق من البيانات المطلوبة
        if (empty($userData["product"]["name"]) || 
            empty($userData["product"]["price"]) || 
            empty($userData["product"]["category"]) || 
            empty($userData["description"]) || 
            empty($userData["brand"]) || 
            empty($userData["images"])) {
            sendTelegramMessage($chatId, "❌ بيانات المنتج غير مكتملة");
            return false;
        }

        // 2. تحضير الصور أولاً - منع التكرار
        $allImages = [];
        $processedImageIds = []; // لتتبع الصور المعالجة
        
        foreach ($userData["images"] as $index => $imageId) {
            // تجنب تكرار نفس الصورة
            if (in_array($imageId, $processedImageIds)) {
                writeLog("Skipping duplicate image ID: " . $imageId);
                continue;
            }
            
            $imageUrl = getTelegramFileUrl($imageId);
            if ($imageUrl) {
                $allImages[] = ['src' => $imageUrl];
                $processedImageIds[] = $imageId;
                writeLog("Image " . (count($allImages)) . " prepared: " . $imageUrl);
            }
        }
        
        // تسجيل عدد الصور المحضرة
        writeLog("Total images prepared for product: " . count($allImages));

        if (empty($allImages)) {
            sendTelegramMessage($chatId, "❌ فشل في تحضير الصور");
            return false;
        }

        // 3. تحضير الألوان والمقاسات كـ attributes مع variation=true
        $attributes = [];
        $colorOptions = [];
        $sizeOptions = [];
        $colorImages = []; // مصفوفة لتخزين صور الألوان
        
        // الألوان
        if (!empty($userData["attributes"]["color"])) {
            $colorAttribute = createColorAttribute();
            $colors = is_array($userData["attributes"]["color"]) ? 
                    $userData["attributes"]["color"] : 
                    preg_split('/[,،]+/', $userData["attributes"]["color"]);
            $colors = array_map('trim', $colors);
            $colors = array_filter($colors);
            
            // ربط صورة واحدة لكل لون حسب ترتيب الرفع (صورة 1 للون 1، صورة 2 للون 2 ...)
            $totalImages = count($allImages);
            $totalColors = count($colors);
            writeLog("Total images: $totalImages, Total colors: $totalColors (1 image per color mapping)");
            
            foreach ($colors as $index => $color) {
                $color = trim($color);
                createColorTerm($color); // تضع كود اللون في الوصف/meta
                $colorOptions[] = $color;
                
                // صورة اللون إن وُجدت
                if ($index < $totalImages) {
                    $colorImages[$color] = [$allImages[$index]];
                } else {
                    $colorImages[$color] = [];
                }
                writeLog("Color: $color, Image index: $index, Images: " . json_encode($colorImages[$color]));
            }
            
            if (!empty($colorOptions)) {
                $attributes[] = [
                    'id' => $colorAttribute['id'],
                    'name' => 'Color',
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'options' => $colorOptions
                ];
            }
            
            // تسجيل الكميات للألوان للتأكد من صحتها
            writeLog("Color quantities: " . json_encode($userData["color_quantities"]));
            writeLog("All color options: " . json_encode($colorOptions));
            writeLog("User data keys: " . json_encode(array_keys($userData)));
            foreach ($colorOptions as $color) {
                $quantity = isset($userData["color_quantities"][$color]) ? $userData["color_quantities"][$color] : 0;
                writeLog("Color: $color, Quantity: $quantity, Images: " . count($colorImages[$color]));
                writeLog("Checking if color '$color' exists in quantities: " . (isset($userData["color_quantities"][$color]) ? "YES" : "NO"));
            }
        }
        // المقاسات
        if (!empty($userData["attributes"]["size"])) {
            $sizeAttribute = createAttribute('Size', 'pa_size');
            $sizes = is_array($userData["attributes"]["size"]) ? 
                    $userData["attributes"]["size"] : 
                    preg_split('/[,،]+/', $userData["attributes"]["size"]);
            $sizes = array_map('trim', $sizes);
            $sizes = array_filter($sizes);
            foreach ($sizes as $size) {
                $size = trim($size);
                createAttributeTerm($sizeAttribute['id'], $size);
                $sizeOptions[] = $size;
            }
            if (!empty($sizeOptions)) {
                $attributes[] = [
                    'id' => $sizeAttribute['id'],
                    'name' => 'Size',
                    'position' => 1,
                    'visible' => true,
                    'variation' => true,
                    'options' => $sizeOptions
                ];
            }
        }

        // 4. بناء بيانات المنتج (variable فقط، مع الألوان والمقاسات)
        $enhancedDescription = $userData["description"];
        
        // تسجيل الكميات قبل إنشاء المنتج
        writeLog("Final check - Color quantities before product creation: " . json_encode($userData["color_quantities"]));
        writeLog("Final check - All user data: " . json_encode($userData));
        writeLog("Final check - Color quantities type: " . gettype($userData["color_quantities"]));
        writeLog("Final check - Color quantities count: " . (isset($userData["color_quantities"]) ? count($userData["color_quantities"]) : "NOT SET"));
        
        // إضافة جميع الصور للمنتج الرئيسي لضمان ظهورها في المعرض
        $productImages = [];
        foreach ($allImages as $index => $image) {
            // إضافة موضع للصورة لضمان ترتيبها بشكل صحيح
            $productImages[] = [
                'src' => $image['src'],
                'position' => $index,
                'name' => 'Product Image ' . ($index + 1)
            ];
        }
        
        $product = [
            'name' => $userData["product"]["name"],
            'type' => 'variable',
            'status' => 'publish',
            'description' => $enhancedDescription,
            'short_description' => $enhancedDescription,
            'manage_stock' => false,
            'stock_status' => 'instock',
            'sku' => isset($userData["product"]["sku"]) && trim($userData["product"]["sku"]) !== '' ? trim($userData["product"]["sku"]) : '',
            'categories' => [
                ['id' => getCategoryIdBySimilarity($userData["product"]["category"])]
            ],
            'brands' => [
                ['id' => getBrandIdBySimilarity($userData["brand"])]
            ],
            'images' => $productImages, // إضافة جميع الصور للمنتج الرئيسي
            'attributes' => $attributes,
            'tags' => array_map(function ($tag) {
                return ['name' => $tag];
            }, $userData["tags"] ?? [])
        ];

        // تعيين اللون الافتراضي ليتوافق مع أول صورة/لون حتى تتغير صورة المعرض مباشرة
        if (!empty($colorOptions)) {
            $product['default_attributes'] = [
                [
                    'id' => $colorAttribute['id'],
                    'option' => $colorOptions[0]
                ]
            ];
        }

        writeLog("Sending product data to WooCommerce: " . json_encode($product));
        writeLog("Product images count: " . count($productImages));

        // 5. إرسال المنتج إلى WooCommerce
        $ch = curl_init($woocommerceUrl . "/wp-json/wc/v3/products");
        curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            writeLog("Curl Error: " . curl_error($ch));
            sendTelegramMessage($chatId, "❌ حدث خطأ في الاتصال: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            $productId = $result['id'];
            writeLog("Product created successfully with ID: " . $productId . ". All images added to main product gallery.");
            // إنشاء متغيرات (variations) لكل تركيبة لون × مقاس أو لون فقط أو مقاس فقط
            writeLog("Starting variations creation. Colors: " . json_encode($colorOptions) . ", Sizes: " . json_encode($sizeOptions));
            if (!empty($colorOptions) && !empty($sizeOptions)) {
                foreach ($colorOptions as $color) {
                    // تحديد الكمية للون الحالي فقط
                    $quantity = isset($userData["color_quantities"][$color]) ? intval($userData["color_quantities"][$color]) : 0;
                    writeLog("Creating variations for color: $color with quantity: $quantity");
                    writeLog("Color quantities array: " . json_encode($userData["color_quantities"]));
                    writeLog("Checking quantity for color '$color': " . (isset($userData["color_quantities"][$color]) ? "FOUND: " . $userData["color_quantities"][$color] : "NOT FOUND"));
                    writeLog("All quantities keys: " . json_encode(array_keys($userData["color_quantities"] ?? [])));
                    
                    // السماح بالكمية 0 - لا تغيير
                    if ($quantity < 0) {
                        writeLog("⚠️ WARNING: Quantity for color '$color' is negative ($quantity) - using 0");
                        $quantity = 0;
                    }
                    
                    writeLog("Final quantity for color '$color': $quantity");
                    
                    // صور هذا اللون
                    $colorVariationImages = isset($colorImages[$color]) ? $colorImages[$color] : [];
                    writeLog("Images for color $color: " . json_encode($colorVariationImages));
                    
                    foreach ($sizeOptions as $size) {
                        $variation = [
                            'regular_price' => (string)$userData["product"]["price"],
                            'manage_stock' => true,
                            'stock_quantity' => $quantity,
                            'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
                            'attributes' => [
                                [
                                    'id' => $colorAttribute['id'],
                                    'option' => $color
                                ],
                                [
                                    'id' => $sizeAttribute['id'],
                                    'option' => $size
                                ]
                            ],
                        ];
                        
                                                    // تعيين صورة واحدة للمتغير حسب لونِه (WooCommerce variation image)
                        // فقط إذا لم تكن الصورة موجودة في المنتج الرئيسي لتجنب التكرار
                        if (!empty($colorVariationImages)) {
                            $variation['image'] = $colorVariationImages[0];
                            writeLog("Added variation image for color: $color, size: $size");
                        }
                            
                            $variationUrl = $woocommerceUrl . "/wp-json/wc/v3/products/{$productId}/variations";
                            $ch = curl_init($variationUrl);
                            curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($variation));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            $variationResponse = curl_exec($ch);
                            $variationResult = json_decode($variationResponse, true);
                            curl_close($ch);
                            
                            if (isset($variationResult['id'])) {
                                writeLog("Created variation for color: $color, size: $size, quantity: $quantity");
                                writeLog("Variation data sent: " . json_encode($variation));
                            } else {
                                writeLog("Failed to create variation for color: $color, size: $size. Response: " . $variationResponse);
                                writeLog("Variation data that failed: " . json_encode($variation));
                            }
                        }
                    }
                } elseif (!empty($colorOptions)) {
                    writeLog("Creating variations for colors only. Colors: " . json_encode($colorOptions));
                    foreach ($colorOptions as $color) {
                        // تحديد الكمية للون الحالي فقط
                        $quantity = isset($userData["color_quantities"][$color]) ? intval($userData["color_quantities"][$color]) : 0;
                        writeLog("Creating variation for color: $color with quantity: $quantity");
                        writeLog("Color quantities array: " . json_encode($userData["color_quantities"]));
                        writeLog("Checking quantity for color '$color': " . (isset($userData["color_quantities"][$color]) ? "FOUND: " . $userData["color_quantities"][$color] : "NOT FOUND"));
                        writeLog("All quantities keys: " . json_encode(array_keys($userData["color_quantities"] ?? [])));
                        
                        // السماح بالكمية 0 - لا تغيير
                        if ($quantity < 0) {
                            writeLog("⚠️ WARNING: Quantity for color '$color' is negative ($quantity) - using 0");
                            $quantity = 0;
                        }
                        
                        writeLog("Final quantity for color '$color': $quantity");
                        
                        // صور هذا اللون
                        $colorVariationImages = isset($colorImages[$color]) ? $colorImages[$color] : [];
                        writeLog("Images for color $color: " . json_encode($colorVariationImages));
                        
                        $variation = [
                            'regular_price' => (string)$userData["product"]["price"],
                            'manage_stock' => true,
                            'stock_quantity' => $quantity,
                            'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
                            'attributes' => [
                                [
                                    'id' => $colorAttribute['id'],
                                    'option' => $color
                                ]
                            ],
                        ];
                        
                        // تعيين صورة واحدة للمتغير حسب لونِه (WooCommerce variation image)
                        // فقط إذا لم تكن الصورة موجودة في المنتج الرئيسي لتجنب التكرار
                        if (!empty($colorVariationImages)) {
                            $variation['image'] = $colorVariationImages[0];
                            writeLog("Added variation image for color: $color");
                        }
                        
                        $variationUrl = $woocommerceUrl . "/wp-json/wc/v3/products/{$productId}/variations";
                        $ch = curl_init($variationUrl);
                        curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($variation));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $variationResponse = curl_exec($ch);
                        $variationResult = json_decode($variationResponse, true);
                        curl_close($ch);
                        
                        if (isset($variationResult['id'])) {
                            writeLog("Created variation for color: $color, quantity: $quantity");
                            writeLog("Variation data sent: " . json_encode($variation));
                        } else {
                            writeLog("Failed to create variation for color: $color. Response: " . $variationResponse);
                            writeLog("Variation data that failed: " . json_encode($variation));
                        }
                    }
                } elseif (!empty($sizeOptions)) {
                    foreach ($sizeOptions as $size) {
                        $variation = [
                            'regular_price' => (string)$userData["product"]["price"],
                            'manage_stock' => false,
                            'stock_status' => 'instock',
                            'attributes' => [
                                [
                                    'id' => $sizeAttribute['id'],
                                    'option' => $size
                                ]
                            ]
                        ];
                        $variationUrl = $woocommerceUrl . "/wp-json/wc/v3/products/{$productId}/variations";
                        $ch = curl_init($variationUrl);
                        curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($variation));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $variationResponse = curl_exec($ch);
                        curl_close($ch);
                    }
                }
                // إعداد رسالة النجاح مع معلومات الكميات
                $imageCount = count($allImages);
                $successMessage = "✅ تم رفع المنتج بنجاح!\n📦 رقم المنتج: " . $productId . "\n📸 تم رفع " . $imageCount . " صور في معرض المنتج الرئيسي\n💡 جميع الصور ستظهر في المعرض بدون تكرار";
            
                if (!empty($userData["color_quantities"])) {
                    $successMessage .= "\n📊 الكميات المحددة لكل لون:";
                    writeLog("Final color quantities for success message: " . json_encode($userData["color_quantities"]));
                    foreach ($userData["color_quantities"] as $color => $quantity) {
                        $successMessage .= "\n🎨 $color: $quantity قطعة";
                        writeLog("Success message - Color: $color, Quantity: $quantity");
                    }
                    $successMessage .= "\n💡 كل لون له كميته الخاصة\n✅ يمكنك وضع كمية 0 للون إذا لم ترد بيعه";
                }
                
                sendTelegramMessage($chatId, $successMessage);
                return true;
            } else {
                $error = isset($result['message']) ? $result['message'] : 'خطأ غير معروف';
                $errorDetails = isset($result['data']['params']) ? json_encode($result['data']['params']) : '';
                writeLog("API Error: " . $error . " Details: " . $errorDetails);
                writeLog("Full API Response: " . $response);
                sendTelegramMessage($chatId, "❌ فشل رفع المنتج: " . $error);
                return false;
            }
        } catch (Exception $e) {
            writeLog("Exception in uploadProduct: " . $e->getMessage());
            sendTelegramMessage($chatId, "❌ حدث خطأ: " . $e->getMessage());
            return false;
        }
    }

    // دالة لإنشاء خاصية جديدة
    function createAttribute($name, $slug) {
        global $woocommerceUrl;
        
        // التحقق من وجود الخاصية
        $attributes = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes", [], 'GET');
        foreach ($attributes as $attr) {
            if ($attr['slug'] === $slug) {
                return $attr;
            }
        }
        
        // إنشاء خاصية جديدة
        $data = [
            'name' => $name,
            'slug' => $slug,
            'type' => 'select', // إبقاء select للمقاسات
            'order_by' => 'menu_order',
            'has_archives' => true
        ];
        
        return apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes", $data);
    }

    // دالة لإنشاء قيمة خاصية
    function createAttributeTerm($attributeId, $name) {
        global $woocommerceUrl;
        
        $slug = sanitizeSlug($name);
        
        // التحقق من وجود القيمة
        $terms = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/$attributeId/terms", [], 'GET');
        foreach ($terms as $term) {
            if ($term['slug'] === $slug) {
                return $term;
            }
        }
        
        // إنشاء قيمة جديدة (للمقاسات فقط)
        $data = [
            'name' => $name,
            'slug' => $slug
        ];
        
        return apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/$attributeId/terms", $data);
    }

    // دالة مساعدة للتحقق من الاتصال بـ WooCommerce
    function testWooCommerceConnection() {
        global $woocommerceUrl, $consumerKey, $consumerSecret;
        
        try {
            $ch = curl_init($woocommerceUrl . "/wp-json/wc/v3/products?per_page=1");
            curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode >= 200 && $httpCode < 300;
        } catch (Exception $e) {
            writeLog("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    function getCategoryIdBySimilarity($categoryName)
    {
        $categories = getAllCategories();
        $bestMatch = null;
        $highest = 0;

        foreach ($categories as $cat) {
            similar_text(mb_strtolower($categoryName), mb_strtolower($cat['name']), $percent);
            if ($percent > $highest) {
                $highest = $percent;
                $bestMatch = $cat;
            }
        }

        return ($highest >= 60) ? $bestMatch['id'] : null;
    }

    function getAllCategories()
    {
        global $woocommerceUrl;
        return apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/categories?per_page=100", [], 'GET');
    }

    function getBrandIdBySimilarity($brandName)
    {
        $brands = getAllBrands();
        $bestMatch = null;
        $highest = 0;

        foreach ($brands as $brand) {
            similar_text(mb_strtolower($brandName), mb_strtolower($brand['name']), $percent);
            if ($percent > $highest) {
                $highest = $percent;
                $bestMatch = $brand;
            }
        }

        return ($highest >= 60) ? $bestMatch['id'] : null;
    }

    function getAllBrands()
    {
        global $woocommerceUrl;
        return apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/brands?per_page=100", [], 'GET');
    }

    function getTelegramFileUrl($fileId)
    {
        global $telegramToken;
        $res = json_decode(file_get_contents("https://api.telegram.org/bot$telegramToken/getFile?file_id=$fileId"), true);

        if (isset($res["result"]["file_path"])) {
            return "https://api.telegram.org/file/bot$telegramToken/" . $res["result"]["file_path"];
        }
        return null;
    }

    function apiRequest($url, $data = [], $method = 'POST')
    {
        global $consumerKey, $consumerSecret;

        try {
            writeLog("Making API request to: " . $url);
            writeLog("Method: " . $method);
            writeLog("Data: " . json_encode($data));

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_USERPWD, "$consumerKey:$consumerSecret");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            if (!empty($data) && $method !== 'GET') {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                writeLog("Sending data: " . $jsonData);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            writeLog("API Response Code: " . $httpCode);
            writeLog("API Response: " . $response);

            if ($error) {
                writeLog("cURL Error: " . $error);
                throw new Exception("API request failed: " . $error);
            }

            curl_close($ch);

            $decoded = json_decode($response, true);

            if ($httpCode >= 400) {
                writeLog("API Error Response: " . $response);
                throw new Exception("API request failed with code $httpCode: " . ($decoded['message'] ?? $response));
            }

            return $decoded;
        } catch (Exception $e) {
            writeLog("API Request Exception: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    function sendTelegramMessage($chatId, $message) {
        global $telegramToken;
        
        $ch = curl_init("https://api.telegram.org/bot$telegramToken/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    function sendTelegramInlineKeyboard($chatId, $message, $buttons) {
        global $telegramToken;

            $ch = curl_init("https://api.telegram.org/bot$telegramToken/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
            $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    function sendTelegramKeyboard($chatId, $text, $keyboard) {
        global $telegramToken;
        
        $ch = curl_init("https://api.telegram.org/bot$telegramToken/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
            curl_close($ch);

        return $response;
    }

    // قراءة البيانات مع قفل مشارك لتجنب قراءة ملف أثناء الكتابة (حل التخزين المتقطع)
    function loadDataWithLock($file) {
        if (!file_exists($file)) return [];
        $fp = fopen($file, 'r');
        if (!$fp) return [];
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $data = json_decode($content, true) ?: [];
            $data = is_array($data) ? array_combine(array_map('strval', array_keys($data)), array_values($data)) : [];
            return $data;
        }
        fclose($fp);
        return [];
    }

    function saveData($data, $file)
    {
        if (is_array($data)) {
            $data = array_combine(array_map('strval', array_keys($data)), array_values($data));
        }
        $fp = fopen($file, 'c+');
        if (!$fp) {
            writeLog("saveData: failed to open file: " . $file);
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            fwrite($fp, $jsonData);
            fflush($fp);
            flock($fp, LOCK_UN);
            writeLog("saveData: Successfully saved data to file. Keys: " . json_encode(array_keys($data)));
        }
        fclose($fp);
    }

    function commandsList()
    {
        return "\n\nالأوامر المتاحة:\n/new - بدء منتج جديد\n/show - عرض البيانات الحالية\n/editname - تعديل الاسم\n/editprice - تعديل السعر\n/editcategory - تعديل التصنيف\n/editdescription - تعديل الوصف\n/editattributes - تعديل الخصائص\n/edittags - تعديل العلامات\n/editbrand - تعديل الماركة\n/deleteimage - حذف آخر صورة\n/upload - رفع المنتج\n/cancel - إلغاء المنتج\n/updatecolors - تحديث الألوان لتكون ملونة\n/forcecolor - إجبار الألوان لتكون متغيرات ملونة";
    }

    function createColorAttribute()
    {
        global $woocommerceUrl;

        // Check if color attribute exists
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/attributes";
        $existingAttrs = apiRequest($url, [], 'GET');

        $colorAttrId = null;
        foreach ($existingAttrs as $attr) {
            if ($attr['name'] === 'Color' || $attr['slug'] === 'pa_color') {
                $colorAttrId = $attr['id'];
                writeLog("[Color Debug] Found existing color attribute with ID: " . $attr['id']);
                
                // تحديث نوع الخاصية إلى image إذا لم تكن كذلك
                if ($attr['type'] !== 'image') {
                    $updateData = [
                        'type' => 'image'
                    ];
                    $updateResponse = apiRequest($url . "/" . $attr['id'], $updateData, 'PUT');
                    writeLog("[Color Debug] Updated attribute type to image. Response: " . json_encode($updateResponse));
                }
                
                break;
            }
        }

        if (!$colorAttrId) {
            $data = [
                'name' => 'Color',
                'slug' => 'pa_color',
                'type' => 'image',
                'order_by' => 'menu_order',
                'has_archives' => true,
                'attribute_public' => 1
            ];
            $response = apiRequest($url, $data, 'POST');
            writeLog("[Color Debug] Created new color attribute. Response: " . json_encode($response));
            return $response;
        }

        return ['id' => $colorAttrId];
    }

    function createColorTerm($color)
    {
        global $woocommerceUrl;

        // Get the color attribute ID first
        $colorAttr = createColorAttribute();
        if (!isset($colorAttr['id'])) {
            writeLog("[Color Debug] Failed to get color attribute ID");
            return ['error' => 'Could not get color attribute ID'];
        }
        $colorAttrId = $colorAttr['id'];

        $colorHex = getColorHex($color);
        $colorSlug = sanitizeSlug(mb_strtolower(trim($color)));
        writeLog("[Color Debug] Processing color: " . $color . ", Hex: " . $colorHex . ", Slug: " . $colorSlug);

        try {
            // First try to get the term directly by slug
            $termResponse = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/{$colorAttrId}/terms?slug=" . $colorSlug, [], 'GET');

            if (!isset($termResponse['error']) && !empty($termResponse)) {
                $term = $termResponse[0];
                writeLog("[Color Debug] Found existing color term with ID: " . $term['id']);
                return $term;
            }

            // If term doesn't exist, create it (الصور موجودة مسبقاً في الموقع)
            $data = [
                'name' => $color,
                'slug' => $colorSlug,
                'description' => $colorHex
            ];

            $url = $woocommerceUrl . "/wp-json/wc/v3/products/attributes/{$colorAttrId}/terms";
            $response = apiRequest($url, $data, 'POST');
            writeLog("[Color Debug] Created new color term. Success: " . (!isset($response['error'])));

            return $response;
        } catch (Exception $e) {
            writeLog("[Color Debug] Error creating/updating color term: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    function getAttributeTerms($attributeSlug)
    {
        global $woocommerceUrl;
        writeLog("Getting attribute terms for: " . $attributeSlug);
        
        $attributeId = null;
        $attributes = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes?per_page=100", [], 'GET');
        writeLog("All attributes: " . json_encode($attributes));
        
        foreach ($attributes as $attr) {
            if ($attr['slug'] === $attributeSlug) {
                $attributeId = $attr['id'];
                writeLog("Found attribute ID: " . $attributeId . " for slug: " . $attributeSlug);
                break;
            }
        }

        if (!$attributeId) {
            writeLog("No attribute found for slug: " . $attributeSlug);
            return [];
        }

        $terms = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/{$attributeId}/terms?per_page=100", [], 'GET');
        writeLog("Retrieved terms for attribute {$attributeId}: " . json_encode($terms));
        return $terms;
    }

    // دالة جديدة لتنظيم المقاسات بشكل احترافي
    function organizeSizes($sizes) {
        $organized = [
            'common' => [],
            'numeric' => [],
            'other' => []
        ];
        
        foreach ($sizes as $size) {
            $name = strtolower(trim($size['name']));
            
            // المقاسات الشائعة
            if (in_array($name, ['xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', 'صغير', 'وسط', 'كبير', 'اكس لارج', 'اكس اكس لارج'])) {
                $organized['common'][] = $size;
            }
            // المقاسات الرقمية
            elseif (is_numeric($name) || preg_match('/^\d+$/', $name)) {
                $organized['numeric'][] = $size;
            }
            // باقي المقاسات
            else {
                $organized['other'][] = $size;
            }
        }
        
        // ترتيب المقاسات الرقمية
        usort($organized['numeric'], function($a, $b) {
            return intval($a['name']) - intval($b['name']);
        });
        
        return $organized;
    }

        // دالة جديدة لإنشاء أزرار المقاسات المحسنة - مبسطة وفعالة
        function createSizeButtons($sizes, $selectedSizes = [], $page = 0) {
            $buttons = [];
            
            // إضافة أزرار التحكم في الأعلى دائماً
            $buttons[] = [
                ["text" => "✅ تم اختيار المقاسات", "callback_data" => "done_sizes"],
                ["text" => "⏭️ تخطي المقاسات", "callback_data" => "skip_sizes"]
            ];
            
            // إضافة أزرار سريعة للمقاسات الشائعة
            $quickButtons = [];
            $commonSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
            foreach ($commonSizes as $size) {
                $isSelected = in_array($size, $selectedSizes);
                $buttonText = $size . ($isSelected ? ' ✓' : '');
                $quickButtons[] = ["text" => $buttonText, "callback_data" => 'size_' . $size];
            }
            if (!empty($quickButtons)) {
                $buttons[] = [["text" => "⚡ مقاسات سريعة", "callback_data" => "size_header_quick"]];
                $buttons[] = array_slice($quickButtons, 0, 3);
                $buttons[] = array_slice($quickButtons, 3, 3);
            }
            
            // إضافة جميع المقاسات مع نظام صفحات بسيط
            $itemsPerPage = 12; // 3 أزرار × 4 صفوف
            $totalPages = ceil(count($sizes) / $itemsPerPage);
            $startIndex = $page * $itemsPerPage;
            $pageItems = array_slice($sizes, $startIndex, $itemsPerPage);
            
            $tempButtons = [];
            foreach ($pageItems as $size) {
                $isSelected = in_array($size['name'], $selectedSizes);
                $buttonText = $size['name'] . ($isSelected ? ' ✓' : '');
                $tempButtons[] = ["text" => $buttonText, "callback_data" => 'size_' . $size['name']];
                if (count($tempButtons) == 3) {
                    $buttons[] = $tempButtons;
                    $tempButtons = [];
                }
            }
            if (!empty($tempButtons)) {
                $buttons[] = $tempButtons;
            }
            
            // إضافة أزرار التنقل بين الصفحات
            if ($totalPages > 1) {
                $navButtons = [];
                if ($page > 0) {
                    $navButtons[] = ["text" => "⬅️ السابق", "callback_data" => "size_page_" . ($page - 1)];
                }
                $navButtons[] = ["text" => "📄 " . ($page + 1) . "/" . $totalPages, "callback_data" => "size_page_info"];
                if ($page < $totalPages - 1) {
                    $navButtons[] = ["text" => "التالي ➡️", "callback_data" => "size_page_" . ($page + 1)];
                }
                $buttons[] = $navButtons;
            }
            
            // إضافة زر مسح الكل في الأسفل
            $buttons[] = [["text" => "🗑️ مسح الكل", "callback_data" => "clear_sizes"]];
            
            return $buttons;
        }



    function getColorHex($color)
    {
        $colorMap = [
            'black' => '#000000',
            'اسود' => '#000000',
            'green' => '#00ff00',
            'اخضر' => '#00ff00',
            'beige' => '#f5f5dc',
            'بيج' => '#f5f5dc',
            'red' => '#ff0000',
            'احمر' => '#ff0000',
            'blue' => '#0000ff',
            'ازرق' => '#0000ff',
            'white' => '#ffffff',
            'ابيض' => '#ffffff',
            'yellow' => '#ffff00',
            'اصفر' => '#ffff00',
            'purple' => '#800080',
            'بنفسجي' => '#800080',
            'pink' => '#ffc0cb',
            'وردي' => '#ffc0cb',
            'brown' => '#a52a2a',
            'بني' => '#a52a2a',
            'gray' => '#808080',
            'رمادي' => '#808080',
            'orange' => '#ffa500',
            'برتقالي' => '#ffa500',
            'navy' => '#000080',
            'navy blue' => '#000080',
            'navyblue' => '#000080',
            'dark blue' => '#000080',
            'darkblue' => '#000080',
            'light blue' => '#87ceeb',
            'lightblue' => '#87ceeb',
            'dark green' => '#006400',
            'darkgreen' => '#006400',
            'light green' => '#90ee90',
            'lightgreen' => '#90ee90',
            'dark red' => '#8b0000',
            'darkred' => '#8b0000',
            'light red' => '#ffcccb',
            'lightred' => '#ffcccb',
            'dark yellow' => '#b8860b',
            'darkyellow' => '#b8860b',
            'light yellow' => '#ffffe0',
            'lightyellow' => '#ffffe0',
            'dark purple' => '#4b0082',
            'darkpurple' => '#4b0082',
            'light purple' => '#e6e6fa',
            'lightpurple' => '#e6e6fa',
            'dark pink' => '#c71585',
            'darkpink' => '#c71585',
            'light pink' => '#ffb6c1',
            'lightpink' => '#ffb6c1',
            'dark brown' => '#654321',
            'darkbrown' => '#654321',
            'light brown' => '#d2691e',
            'lightbrown' => '#d2691e',
            'dark gray' => '#404040',
            'darkgray' => '#404040',
            'light gray' => '#d3d3d3',
            'lightgray' => '#d3d3d3',
            'dark orange' => '#ff8c00',
            'darkorange' => '#ff8c00',
            'light orange' => '#ffa07a',
            'lightorange' => '#ffa07a',
            'teal' => '#008080',
            'teal blue' => '#008080',
            'tealblue' => '#008080',
            'sky blue' => '#87ceeb',
            'skyblue' => '#87ceeb',
            'royal blue' => '#4169e1',
            'royalblue' => '#4169e1',
            'silver' => '#c0c0c0',
            'فضي' => '#c0c0c0',
            'gold' => '#ffd700',
            'ذهبي' => '#ffd700',
            'bronze' => '#cd7f32',
            'برونزي' => '#cd7f32'
        ];

        $colorLower = mb_strtolower(trim($color));
        return isset($colorMap[$colorLower]) ? $colorMap[$colorLower] : '#ff0000';
    }

    function sanitizeSlug($string) {
        // تحويل الأحرف العربية إلى إنجليزية للألوان
        $arabicToEnglish = [
            'احمر' => 'red',
            'اخضر' => 'green',
            'ازرق' => 'blue',
            'اسود' => 'black',
            'ابيض' => 'white',
            'اصفر' => 'yellow',
            'برتقالي' => 'orange',
            'بنفسجي' => 'purple',
            'رمادي' => 'gray',
            'بني' => 'brown',
            'وردي' => 'pink',
            'بيج' => 'beige',
            // إضافة المزيد من الترجمات حسب الحاجة
            
            // المقاسات
            'صغير' => 'small',
            'وسط' => 'medium',
            'كبير' => 'large',
            'اكس لارج' => 'xlarge',
            'اكس اكس لارج' => 'xxlarge'
        ];

        $string = mb_strtolower(trim($string));
        
        // التحقق من وجود ترجمة
        if (isset($arabicToEnglish[$string])) {
            $string = $arabicToEnglish[$string];
        }

        // تنظيف النص
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = trim($string, '-');
        $string = preg_replace('/-+/', '-', $string);
        
        return $string;
    }

    function writeLog($message) {
        $logFile = __DIR__ . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    // دالة لتنظيف الصور المكررة (لم تعد ضرورية بعد إصلاح المشكلة)
    function cleanDuplicateImages(&$userData) {
        if (isset($userData["images"]) && is_array($userData["images"])) {
            $originalCount = count($userData["images"]);
            $userData["images"] = array_unique($userData["images"]);
            $cleanedCount = count($userData["images"]);
            
            if ($originalCount !== $cleanedCount) {
                writeLog("Cleaned duplicate images: removed " . ($originalCount - $cleanedCount) . " duplicates");
                return true;
            }
        }
        return false;
    }

    // دالة لتحديث جميع الألوان الموجودة لتكون من نوع image
    function updateExistingColorsToColorType() {
        global $woocommerceUrl;
        
        writeLog("Starting update of existing colors to image type...");
        
        // الحصول على خاصية الألوان
        $colorAttr = createColorAttribute();
        if (!isset($colorAttr['id'])) {
            writeLog("Failed to get color attribute");
            return false;
        }
        
        $colorAttrId = $colorAttr['id'];
        
        // تحديث نوع الخاصية إلى image
        $updateAttributeData = [
            'type' => 'image'
        ];
        
        $updateResponse = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/{$colorAttrId}", $updateAttributeData, 'PUT');
        
        if (isset($updateResponse['error'])) {
            writeLog("Failed to update attribute type. Error: " . json_encode($updateResponse));
            return false;
        }
        
        writeLog("Successfully updated attribute to image type");
        return true;
    }

    // دالة لإجبار الألوان لتكون متغيرات ملونة
    function forceColorsAsVariations() {
        global $woocommerceUrl;
        
        writeLog("Starting force colors as variations...");
        
        // الحصول على خاصية الألوان
        $colorAttr = createColorAttribute();
        if (!isset($colorAttr['id'])) {
            writeLog("Failed to get color attribute");
            return false;
        }
        
        $colorAttrId = $colorAttr['id'];
        
        // تحديث نوع الخاصية لتكون متغيرات
        $updateAttributeData = [
            'variation' => true,
            'type' => 'image'
        ];
        
        $updateResponse = apiRequest($woocommerceUrl . "/wp-json/wc/v3/products/attributes/{$colorAttrId}", $updateAttributeData, 'PUT');
        
        if (isset($updateResponse['error'])) {
            writeLog("Failed to update attribute type. Error: " . json_encode($updateResponse));
            return false;
        }
        
        writeLog("Successfully updated attribute to image type with variations");
        return true;
    }





    // إضافة دالة جديدة لحذف الرسائل
    function sendTelegramDeleteMessage($chatId, $messageId) {
        global $telegramToken;
        try {
            $params = [
                'chat_id' => $chatId,
                'message_id' => $messageId
            ];
            
            $ch = curl_init("https://api.telegram.org/bot$telegramToken/deleteMessage");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                writeLog("Error deleting message: " . curl_error($ch));
            }
            
            curl_close($ch);
            
            $result = json_decode($response, true);
            if (!$result['ok']) {
                writeLog("Telegram API Error while deleting message: " . json_encode($result));
            }
        } catch (Exception $e) {
            writeLog("Exception while deleting message: " . $e->getMessage());
        }
    }

    // دالة جديدة لمعالجة الإدخالات النصية
    function handleTextInput($userId, $chatId, $text, &$usersData) {
        global $dataFile;
        
        switch ($usersData[$userId]["edit_mode"] ?? $usersData[$userId]["step"] ?? null) {
            case "editname":
                $usersData[$userId]["product"]["name"] = $text;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث الاسم بنجاح.");
                
                // إعادة عرض لوحة المفاتيح الرئيسية
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                break;
                
            case "editprice":
                if (is_numeric($text) && $text > 0) {
                    $usersData[$userId]["product"]["price"] = $text;
                    $usersData[$userId]["edit_mode"] = null;
                    saveData($usersData, $dataFile);
                    sendTelegramMessage($chatId, "✅ تم تحديث السعر بنجاح.");
                    
                    // إعادة عرض لوحة المفاتيح الرئيسية
                    $mainKeyboard = [
                        ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                        ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                        ['❌ إلغاء المنتج']
                    ];
                    
                    $replyMarkup = [
                        'keyboard' => $mainKeyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                } else {
                    sendTelegramMessage($chatId, "⚠️ الرجاء إدخال سعر صالح.");
                }
                break;

            case "editsku":
                $usersData[$userId]["product"]["sku"] = trim($text);
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث رمز المنتج (SKU) بنجاح.");
                
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                break;

                
            case "editdescription":
                $usersData[$userId]["description"] = $text;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث الوصف بنجاح.");
                
                // إعادة عرض لوحة المفاتيح الرئيسية
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                break;
                
            case "edittags":
                // تقسيم العلامات باستخدام عدة أنواع من الفواصل
                $tags = preg_split('/[,،;؛\s]+/', $text);
                $tags = array_map('trim', $tags);
                $tags = array_filter($tags); // إزالة القيم الفارغة
                
                $usersData[$userId]["tags"] = $tags;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث العلامات بنجاح.");
                
                // إعادة عرض لوحة المفاتيح الرئيسية
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                break;

            case "editquantities":
                if (!is_numeric($text) || $text < 0) {
                    sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم صحيح موجب أو 0 للكمية:");
                    return;
                }
                
                $colorName = $usersData[$userId]["current_color"];
                $usersData[$userId]["color_quantities"][$colorName] = intval($text);
                $usersData[$userId]["edit_mode"] = null;
                unset($usersData[$userId]["current_color"]);
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم تحديث كمية اللون '$colorName': $text");
                
                // إعادة عرض لوحة المفاتيح الرئيسية
                $mainKeyboard = [
                    ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
                    ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
                    ['❌ إلغاء المنتج']
                ];
                
                $replyMarkup = [
                    'keyboard' => $mainKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "اختر من القائمة:", $replyMarkup);
                break;
                

                
            default:
                // إذا لم تكن في وضع التعديل، تجاهل النص
                break;
        }
    }

    // دالة لتحويل اسم اللون العربي إلى إنجليزي
    function getColorEnglishName($color) {
        $colorMap = [
            'احمر' => 'Red',
            'اخضر' => 'Green',
            'ازرق' => 'Blue',
            'اسود' => 'Black',
            'ابيض' => 'White',
            'اصفر' => 'Yellow',
            'برتقالي' => 'Orange',
            'بنفسجي' => 'Purple',
            'رمادي' => 'Gray',
            'بني' => 'Brown',
            'وردي' => 'Pink',
            'بيج' => 'Beige'
        ];
        
        $colorLower = mb_strtolower(trim($color));
        return isset($colorMap[$colorLower]) ? $colorMap[$colorLower] : ucfirst($color);
    }

    // دالة لتحويل اسم المقاس العربي إلى إنجليزي
    function getSizeEnglishName($size) {
        $sizeMap = [
            'صغير' => 'Small',
            'وسط' => 'Medium',
            'كبير' => 'Large',
            'اكس لارج' => 'XLarge',
            'اكس اكس لارج' => 'XXLarge'
        ];
        
        $sizeLower = mb_strtolower(trim($size));
        return isset($sizeMap[$sizeLower]) ? $sizeMap[$sizeLower] : ucfirst($size);
    }

    // تم إزالة دوال ربط الألوان بالصور - لم تعد مطلوبة

    // دالة لحفظ المستخدمين المصرح لهم
    function saveAuthorizedUsers($authorizedUsers, $file) {
        file_put_contents($file, json_encode($authorizedUsers));
    }

    // ===== ملاحظات مهمة =====
    /*
    🔒 نظام كلمة المرور:
    - كلمة المرور الافتراضية: obt2024
    - يمكن تغييرها من المتغير $botPassword أعلاه
    - المستخدمون المصرح لهم محفوظون في ملف authorized_users.json
    - يمكن مسح جميع المستخدمين باستخدام أمر /clearusers

    📋 الأوامر المتاحة:
    - /start - بدء البوت
    - /new - إضافة منتج جديد  
    - /show - عرض البيانات الحالية
    - /logout - تسجيل الخروج
    - /users - عرض المستخدمين المصرح لهم
    - /clearusers - مسح جميع المستخدمين

    💡 نصائح الأمان:
    - غيّر كلمة المرور بشكل دوري
    - لا تشارك كلمة المرور مع أي شخص غير مصرح له
    - استخدم أمر /users لمراقبة المستخدمين المصرح لهم
    - في حالة الطوارئ، استخدم /clearusers لمسح جميع الصلاحيات
    */





