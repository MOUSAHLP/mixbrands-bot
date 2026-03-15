<?php
// test from github
// ===== بوت إضافة المنتجات مع نظام كلمة المرور =====
// https://api.telegram.org/bot8045148484:AAHCFv9IbKti1ilA4HI3h84AZ9I8RfNqrqc/setWebhook?url=https://mesbah.ae/ar/bot.php
// ===== إعدادات البوت =====
$telegramToken = '8045148484:AAHCFv9IbKti1ilA4HI3h84AZ9I8RfNqrqc';
$woocommerceUrl = 'https://mesbah.ae/ar/';
$consumerKey = 'ck_3d79dd35c5fe9059de5882dbf18a2e6c653095a4';
$consumerSecret = 'cs_fc64fbb8074cebebd06122a3518803ddc9126a00';

// ===== نظام كلمة المرور =====
$botPassword = 'mesbah2024'; // كلمة المرور للبوت - يمكنك تغييرها هنا
// 💡 لتغيير كلمة المرور، قم بتعديل القيمة أعلاه
$dataFile = __DIR__ . '/temp_data.json';
$authorizedUsersFile = __DIR__ . '/authorized_users.json';

// إنشاء ملف المستخدمين المصرح لهم إذا لم يكن موجوداً
if (!file_exists($authorizedUsersFile)) {
    file_put_contents($authorizedUsersFile, json_encode([]));
}

if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
$usersData = json_decode(file_get_contents($dataFile), true);
$authorizedUsers = json_decode(file_get_contents($authorizedUsersFile), true);

$content = file_get_contents("php://input");
$update = json_decode($content, true);

// تسجيل البيانات المستلمة للتشخيص
writeLog("=== NEW REQUEST ===");
if ($content) {
    writeLog("Raw input received: " . substr($content, 0, 500) . "...");
    writeLog("Decoded update: " . json_encode($update));
} else {
    writeLog("No input received - this might be a test request");
}

// استقبال الرسائل النصية
if (isset($update["message"])) {
    try {
        $chatId = $update["message"]["chat"]["id"];
        $userId = $update["message"]["from"]["id"];
        
        writeLog("Processing message from user: $userId, chat: $chatId");
        writeLog("Message has text: " . (isset($update["message"]["text"]) ? "yes" : "no"));
        writeLog("Message has photo: " . (isset($update["message"]["photo"]) ? "yes" : "no"));
        
        // ===== التحقق من كلمة المرور =====
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
    
        if (!isset($usersData[$userId])) {
            $usersData[$userId] = [
                "product" => [],
                "images" => [],
                "step" => null,
                "edit_mode" => null,
                "short_description" => "",
                "long_description" => "",
                "additional_info" => [],
                "stock_quantity" => 0
            ];
        }

    if (isset($update["message"]["text"])) {
        $text = trim($update["message"]["text"]);
        
        switch (strtolower($text)) {
            case "/start":
                sendTelegramMessage($chatId, "مرحباً بك في بوت إضافة المنتجات إلى متجرك!\n\nأرسل /new للبدء بإضافة منتج جديد.\n\n✅ المنتجات من نوع simple (بسيطة)\n✅ يحتاج: اسم، رمز المنتج، وصف قصير، سعر، مخزون، مجموعات، وصف طويل، معلومات إضافية، صور\n\n🔒 البوت محمي بكلمة مرور للأمان\n📋 الأوامر المتاحة:\n• /new - إضافة منتج جديد\n• /show - عرض البيانات الحالية\n• /logout - تسجيل الخروج\n• /users - عرض المستخدمين المصرح لهم\n• /clearusers - مسح جميع المستخدمين");
                break;

            case "/logout":
                // إزالة المستخدم من المستخدمين المصرح لهم
                unset($authorizedUsers[$userId]);
                saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
                sendTelegramMessage($chatId, "🔒 تم تسجيل الخروج بنجاح.\n\nلإعادة الدخول، أرسل كلمة المرور مرة أخرى.");
                return;

            case "/users":
                // عرض قائمة المستخدمين المصرح لهم
                if (count($authorizedUsers) > 0) {
                    $usersList = "👥 المستخدمين المصرح لهم:\n\n";
                    foreach ($authorizedUsers as $uid => $userData) {
                        $username = $userData['username'] ?? 'غير محدد';
                        $authorizedAt = $userData['authorized_at'] ?? 'غير محدد';
                        $usersList .= "🆔 ID: $uid\n👤 المستخدم: @$username\n📅 تاريخ التفعيل: $authorizedAt\n\n";
                    }
                    $usersList .= "💡 إجمالي المستخدمين: " . count($authorizedUsers);
                } else {
                    $usersList = "📭 لا يوجد مستخدمين مصرح لهم حالياً.";
                }
                sendTelegramMessage($chatId, $usersList);
                break;

            case "/clearusers":
                // مسح جميع المستخدمين المصرح لهم
                $authorizedUsers = [];
                saveAuthorizedUsers($authorizedUsers, $authorizedUsersFile);
                sendTelegramMessage($chatId, "🗑️ تم مسح جميع المستخدمين المصرح لهم بنجاح.");
                break;

            case "/new":
                $usersData[$userId] = [
                    "product" => [],
                    "images" => [],
                    "step" => "name",
                    "edit_mode" => null,
                    "short_description" => "",
                    "long_description" => "",
                    "additional_info" => [],
                    "stock_quantity" => 0,
                    "sku" => "",
                    "product_id" => "",
                    "barcode" => "",
                    "categories" => [],
                    "product_type" => "simple"
                ];
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم بدء منتج جديد!\n\n📝 الرجاء إدخال اسم المنتج (مثال: سلسلة كهرمان طبيعية):\n\n💡 ستتم مطالبتك بإدخال:\n• الاسم (اسم المنتج الكامل)\n• رقم/رمز المنتج (Product ID) - مثل: 25748 أو ABC123\n• رمز SKU (أرقام أو أحرف) - مثل: SKU-25748 أو 25748\n• الباركود (Barcode) - مثل: 1234567890123\n• السعر\n• المخزون\n• المجموعات\n• الوصف الطويل\n• المعلومات الإضافية (سيتم توليد الوصف القصير تلقائياً)\n• الصور");
                break;

            case "/show":
            case "📋 عرض البيانات الحالية":
                try {
                    $prod = $usersData[$userId]["product"] ?? [];
                    $msg = "📦 بيانات المنتج الحالية:\n\n";
                    
                    // الاسم
                    $msg .= "📝 الاسم: " . ($prod["name"] ?? "غير محدد") . "\n";
                    
                    // نوع المنتج
                    $typeNames = [
                        'simple' => 'بسيط',
                        'variable' => 'متغير',
                        'grouped' => 'مجموعة',
                        'external' => 'خارجي'
                    ];
                    $productType = $usersData[$userId]["product_type"] ?? "simple";
                    $productTypeName = $typeNames[$productType] ?? $productType;
                    $msg .= "🏷️ النوع: " . $productTypeName . "\n";
                    
                    // رمز المنتج
                    $msg .= "🏷️ رمز SKU: " . ($usersData[$userId]["sku"] ?? "غير محدد") . "\n";
                    
                    // رقم المنتج
                    $msg .= "🆔 رقم المنتج: " . ($usersData[$userId]["product_id"] ?? "غير محدد") . "\n";
                    
                    // الباركود
                    $msg .= "📊 الباركود: " . ($usersData[$userId]["barcode"] ?? "غير محدد") . "\n";
                    
                    // الوصف القصير
                    $allAdditionalInfo = $usersData[$userId]["additional_info"] ?? [];
                    if (!empty($usersData[$userId]["current_additional_info"])) {
                        $allAdditionalInfo = array_merge($allAdditionalInfo, $usersData[$userId]["current_additional_info"]);
                    }
                    $shortDescription = generateShortDescription($usersData[$userId]["product"]["name"] ?? "", $allAdditionalInfo);
                    $msg .= "📄 الوصف القصير: " . $shortDescription . "\n";
                    
                    // السعر
                    $msg .= "💰 السعر: " . ($prod["price"] ?? "غير محدد") . "\n";
                    
                    // المخزون
                    $msg .= "📦 المخزون: " . ($usersData[$userId]["stock_quantity"] ?? "غير محدد") . "\n";
                    
                    // المجموعات
                    if (!empty($usersData[$userId]["categories"])) {
                        $msg .= "📂 المجموعات: " . implode(', ', $usersData[$userId]["categories"]) . "\n";
                    } else {
                        $msg .= "📂 المجموعات: غير محددة\n";
                    }
                    
                    // الوصف الطويل
                    $msg .= "📖 الوصف الطويل: " . ($usersData[$userId]["long_description"] ?? "غير محدد") . "\n";
                    
                    // عدد الصور
                    $imageCount = isset($usersData[$userId]["images"]) ? count($usersData[$userId]["images"]) : 0;
                    $msg .= "📸 عدد الصور: " . $imageCount . "\n\n";
                    
                    // المعلومات الإضافية
                    $allAdditionalInfo = $usersData[$userId]["additional_info"] ?? [];
                    if (!empty($usersData[$userId]["current_additional_info"])) {
                        $allAdditionalInfo = array_merge($allAdditionalInfo, $usersData[$userId]["current_additional_info"]);
                    }
                    
                    if (!empty($allAdditionalInfo)) {
                        $msg .= "ℹ️ المعلومات الإضافية:\n";
                        foreach ($allAdditionalInfo as $key => $value) {
                            $msg .= "   • $key: $value\n";
                        }
                    } else {
                        $msg .= "ℹ️ المعلومات الإضافية: غير محددة\n";
                    }
                    
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
            case "/upload":
                writeLog("Upload request received from user: " . $userId);
                
                if (empty($usersData[$userId]["images"])) {
                    sendTelegramMessage($chatId, "⚠️ الرجاء إضافة صورة واحدة على الأقل قبل رفع المنتج.");
                    return;
                }
                
                // التحقق من اكتمال البيانات
                $productName = $usersData[$userId]["product"]["name"] ?? "";
                $productPrice = $usersData[$userId]["product"]["price"] ?? "";
                $longDescription = $usersData[$userId]["long_description"] ?? "";
                $barcode = $usersData[$userId]["barcode"] ?? "";
                $sku = $usersData[$userId]["sku"] ?? "";
                
                // تسجيل البيانات للتشخيص
                writeLog("Validation check for user $userId:");
                writeLog("Name: " . (!empty($productName) ? "exists" : "missing") . " - Value: " . substr($productName, 0, 50));
                writeLog("Price: " . (!empty($productPrice) ? "exists" : "missing") . " - Value: " . $productPrice);
                writeLog("Long Description: " . (!empty($longDescription) ? "exists" : "missing") . " - Length: " . strlen($longDescription));
                writeLog("Barcode: " . (!empty($barcode) ? "exists" : "missing") . " - Value: " . $barcode);
                writeLog("SKU: " . (!empty($sku) ? "exists" : "missing") . " - Value: " . $sku);
                
                if (!empty($productName) &&
                    !empty($productPrice) &&
                    !empty($longDescription) &&
                    !empty($barcode) &&
                    !empty($sku)) {
                    
                    writeLog("Starting product upload for user: " . $userId);
                    sendTelegramMessage($chatId, "⏳ جاري رفع المنتج...");
                    
                    try {
                        // رفع المنتج
                        $result = uploadProduct($usersData[$userId], $chatId);
                        writeLog("Upload result: " . ($result ? "success" : "failed"));
                        
                        if ($result === true) {
                            sendTelegramMessage($chatId, "✅ تم رفع المنتج بنجاح!\n\nيمكنك إضافة منتج جديد باستخدام /new");
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
                            
                            sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
                        }
                    } catch (Exception $e) {
                        writeLog("Error during upload: " . $e->getMessage());
                        sendTelegramMessage($chatId, "❌ حدث خطأ أثناء رفع المنتج: " . $e->getMessage());
                    }
                } else {
                    $missingFields = [];
                    if (empty($productName)) $missingFields[] = "الاسم";
                    if (empty($productPrice)) $missingFields[] = "السعر";
                    if (empty($longDescription)) $missingFields[] = "الوصف";
                    if (empty($barcode)) $missingFields[] = "الباركود";
                    if (empty($sku)) $missingFields[] = "رمز SKU";
                    
                    $missingText = implode(", ", $missingFields);
                    $errorMessage = "⚠️ لا يمكن الرفع - البيانات الناقصة:\n\n";
                    foreach ($missingFields as $field) {
                        $icon = "📄";
                        if ($field === "السعر") $icon = "💰";
                        elseif ($field === "الوصف") $icon = "📄";
                        $errorMessage .= "$icon $field\n";
                    }
                    $errorMessage .= "\n💡 الطريقة الصحيحة: أرسل /new ثم أدخل بالترتيب:\n";
                    $errorMessage .= "1️⃣ اسم المنتج\n";
                    $errorMessage .= "2️⃣ السعر\n";
                    $errorMessage .= "3️⃣ رمز SKU\n";
                    $errorMessage .= "4️⃣ الوصف\n";
                    $errorMessage .= "5️⃣ العلامات\n";
                    $errorMessage .= "6️⃣ اختر التصنيف والماركة والألوان والمقاسات\n";
                    $errorMessage .= "7️⃣ بعدها أضف الصور واضغط رفع المنتج";
                    
                    sendTelegramMessage($chatId, $errorMessage);
                }
                break;

            case "📦 إضافة منتج جديد":
                $usersData[$userId] = [
                    "product" => [],
                    "images" => [],
                    "step" => "name",
                    "edit_mode" => null,
                    "short_description" => "",
                    "long_description" => "",
                    "additional_info" => [],
                    "stock_quantity" => 0,
                    "sku" => "",
                    "product_id" => "",
                    "barcode" => "",
                    "categories" => [],
                    "product_type" => "simple"
                ];
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم بدء منتج جديد!\n\n📝 الرجاء إدخال اسم المنتج (مثال: سلسلة كهرمان طبيعية):\n\n💡 ستتم مطالبتك بإدخال:\n• الاسم (اسم المنتج الكامل)\n• رقم/رمز المنتج (Product ID) - مثل: 25748 أو ABC123\n• رمز SKU (أرقام أو أحرف) - مثل: SKU-25748 أو 25748\n• الباركود (Barcode) - مثل: 1234567890123\n• السعر\n• المخزون\n• المجموعات\n• الوصف الطويل\n• المعلومات الإضافية (سيتم توليد الوصف القصير تلقائياً)\n• الصور");
                break;

            case "✏️ تعديل البيانات":
                $editKeyboard = [
                    ['تعديل الاسم', 'تعديل رقم المنتج'],
                    ['تعديل رمز SKU', 'تعديل الباركود'],
                    ['تعديل السعر', 'تعديل المخزون'],
                    ['تعديل الفئات'],
                    ['تعديل الوصف الطويل', 'تعديل المعلومات الإضافية'],
                    ['رجوع']
                ];
                
                $replyMarkup = [
                    'keyboard' => $editKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "✏️ اختر ما تريد تعديله:", $replyMarkup);
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

            case "❌ إلغاء المنتج":
            case "/cancel":
                unset($usersData[$userId]);
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "❌ تم إلغاء المنتج بالكامل. يمكنك البدء من جديد بإرسال /new");
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
                
                sendTelegramKeyboard($chatId, "✅ تم العودة للقائمة الرئيسية. اختر من القائمة:", $replyMarkup);
                break;

            // معالجة أزرار التعديل
            case "تعديل الاسم":
                $usersData[$userId]["edit_mode"] = "editname";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📝 الرجاء إدخال الاسم الجديد:");
                break;





            case "تعديل رقم المنتج":
                $usersData[$userId]["edit_mode"] = "editproductid";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "🆔 الرجاء إدخال رقم المنتج الجديد:");
                break;

            case "تعديل رمز SKU":
                $usersData[$userId]["edit_mode"] = "editsku";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "🏷️ الرجاء إدخال رمز SKU الجديد (أرقام أو أحرف):");
                break;

            case "تعديل الباركود":
                $usersData[$userId]["edit_mode"] = "editbarcode";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📊 الرجاء إدخال الباركود الجديد:");
                break;



            case "تعديل السعر":
                $usersData[$userId]["edit_mode"] = "editprice";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "💰 الرجاء إدخال السعر الجديد:");
                break;

            case "تعديل المخزون":
                $usersData[$userId]["edit_mode"] = "editstock";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📦 الرجاء إدخال كمية المخزون الجديدة:");
                break;

            case "تعديل الفئات":
                $usersData[$userId]["edit_mode"] = "editcategories";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                
                // جلب الفئات من الموقع
                $categories = getWordPressCategories();
                if (!empty($categories)) {
                    // عرض الفئات الرئيسية كأزرار
                    $categoryButtons = [];
                    $currentRow = [];
                    
                    foreach ($categories as $category) {
                        $currentRow[] = $category['name'];
                        
                        if (count($currentRow) == 2) {
                            $categoryButtons[] = $currentRow;
                            $currentRow = [];
                        }
                    }
                    
                    if (!empty($currentRow)) {
                        $categoryButtons[] = $currentRow;
                    }
                    
                    
                    $replyMarkup = [
                        'keyboard' => $categoryButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    // حفظ البيانات المؤقتة
                    $usersData[$userId]["available_categories"] = $categories;
                    saveData($usersData, $dataFile);
                    
                    sendTelegramKeyboard($chatId, "📂 اختر الفئة الرئيسية من القائمة أدناه (فقط الفئات الرئيسية):", $replyMarkup);
                } else {
                    sendTelegramMessage($chatId, "📂 الرجاء إدخال الفئات يدوياً (مثال: كهرمان، خرز، إكسسوارات):");
                }
                break;

            case "تعديل الوصف الطويل":
                $usersData[$userId]["edit_mode"] = "editlongdesc";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "📖 الرجاء إدخال الوصف الطويل الجديد:");
                break;

            case "تعديل المعلومات الإضافية":
                $usersData[$userId]["edit_mode"] = "editadditionalinfo";
                $usersData[$userId]["step"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "ℹ️ الرجاء إدخال المعلومات الإضافية:\n\n💡 يمكنك:\n• كتابة 'النوع:' لجلب أنواع من الموقع\n• كتابة 'عدد الخرز:' لجلب أعداد من الموقع\n• كتابة 'الوزن:' لجلب أوزان من الموقع\n• كتابة 'نوع القصة:' لجلب أنواع القصة من الموقع\n• كتابة 'اسم الخراط:' لجلب أسماء الخراط من الموقع\n• كتابة 'نوع التطعيم:' لجلب أنواع التطعيم من الموقع\n• أو كتابة أي معلومات أخرى يدوياً\n\nمثال:\nالنوع:\nعدد الخرز:\nالوزن:\nنوع القصة:\nاسم الخراط:\nنوع التطعيم:\nالقياس: 8.5 - 11.5 مم");
                break;

            default:
                // معالجة الإدخالات النصية حسب الخطوة أو وضع التعديل
                handleTextInput($userId, $chatId, $text, $usersData);
                break;
        }
        writeLog("Finished processing text message");
    } else {
        writeLog("Message does not contain text");
    }

    // معالجة الصور
    writeLog("Checking for photo in message...");
    if (isset($update["message"]["photo"])) {
        writeLog("Photo detected in message!");
        $chatId = $update["message"]["chat"]["id"];
        $userId = $update["message"]["from"]["id"];
        
        writeLog("Photo received from user: $userId, chat: $chatId");
        
        if (!isset($usersData[$userId])) {
            $usersData[$userId] = [
                "product" => [],
                "images" => [],
                "step" => null,
                "edit_mode" => null,
                "short_description" => "",
                "long_description" => "",
                "additional_info" => [],
                "stock_quantity" => 0,
                "sku" => "",
                "product_id" => "",
                "barcode" => "",
                "categories" => []
            ];
            writeLog("Created new user data entry for user: $userId");
        }

        $photos = $update["message"]["photo"];
        $fileId = end($photos)["file_id"];
        writeLog("Photo file_id: $fileId, Current step: " . ($usersData[$userId]["step"] ?? "null"));

        // السماح باستقبال الصور في أي وقت (ليس فقط عندما تكون الخطوة "images")
        // إذا لم تكن الخطوة "images"، قم بتعيينها تلقائياً
        if ($usersData[$userId]["step"] !== "images") {
            // إذا كان المستخدم في مرحلة إدخال البيانات، انتقل تلقائياً لمرحلة الصور
            if (in_array($usersData[$userId]["step"], ["additional_info", "long_description", null])) {
                $usersData[$userId]["step"] = "images";
                saveData($usersData, $dataFile);
                writeLog("Auto-set step to 'images' for user: $userId");
            } else {
                // إذا كان في مرحلة أخرى، أرسل رسالة توضيحية
                sendTelegramMessage($chatId, "⚠️ الرجاء إكمال إدخال البيانات أولاً قبل إرسال الصور.\n\n💡 استخدم /show لعرض البيانات الحالية.");
                return;
            }
        }

        if (!isset($usersData[$userId]["images"])) {
            $usersData[$userId]["images"] = [];
        }

        // منع تكرار نفس الصورة
        if (in_array($fileId, $usersData[$userId]["images"])) {
            writeLog("Duplicate image detected, ignoring: $fileId");
            sendTelegramMessage($chatId, "⚠️ هذه الصورة موجودة مسبقاً. تم تجاهلها.");
            return;
        }

        $usersData[$userId]["images"][] = $fileId;
        saveData($usersData, $dataFile);
        writeLog("Image saved successfully. Total images: " . count($usersData[$userId]["images"]));

        $imageCount = count($usersData[$userId]["images"]);

        // إرسال رسالة تأكيد باستخدام الدالة المخصصة
        $confirmMessage = "✅ تم استلام الصورة رقم $imageCount";
        writeLog("Sending confirmation message: $confirmMessage");
        $result = sendTelegramMessage($chatId, $confirmMessage);
        
        if ($result === false) {
            writeLog("Failed to send confirmation message");
        }

        // ثم إرسال لوحة المفاتيح
        $keyboard = [
            ['📋 عرض البيانات الحالية', '📤 رفع المنتج'],
            ['🗑️ حذف آخر صورة', '✏️ تعديل البيانات'],
            ['❌ إلغاء المنتج']
        ];

        $replyMarkup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        writeLog("Sending keyboard to user: $userId");
        $keyboardResult = sendTelegramKeyboard($chatId, "📸 يمكنك إرسال المزيد من الصور أو اختيار أحد الخيارات التالية:", $replyMarkup);
        
        if ($keyboardResult === false) {
            writeLog("Failed to send keyboard");
        }
        
        return;
    }
    } catch (Exception $e) {
        writeLog("Error processing message: " . $e->getMessage());
    }
}

// معالجة الإدخالات النصية حسب الخطوة
if (isset($update["message"]["text"])) {
    $text = trim($update["message"]["text"]);
    $chatId = $update["message"]["chat"]["id"];
    $userId = $update["message"]["from"]["id"];
    
    // التأكد من وجود بيانات المستخدم قبل معالجة الخطوات
    if (!isset($usersData[$userId]) || !array_key_exists("step", $usersData[$userId])) {
        writeLog("Step handler skipped - user $userId has no session data");
    } else {
        writeLog("Step handler - user $userId, step: " . ($usersData[$userId]["step"] ?? "null"));
    }
    
    if (isset($usersData[$userId]["step"]) && $usersData[$userId]["step"] === "name") {
    // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
        "📦 إضافة منتج جديد", 
        "📋 عرض البيانات الحالية", 
        "📤 رفع المنتج", 
        "✏️ تعديل البيانات",
        "🗑️ حذف آخر صورة", 
        "❌ إلغاء المنتج",
        "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال اسم المنتج الفعلي وليس أحد الأزرار!\n\n📝 مثال: سلسلة كهرمان طبيعية");
            return;
        }
        
        $usersData[$userId]["product"]["name"] = $text;
        $usersData[$userId]["step"] = "product_id";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ الاسم: $text\n🆔 الرجاء إدخال رقم/رمز المنتج (مثال: 25748 أو ABC123):");
        return;
    }
    

    

    
    if (isset($usersData[$userId]["step"]) && $usersData[$userId]["step"] === "product_id") {
        // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
            "📦 إضافة منتج جديد", 
            "📋 عرض البيانات الحالية", 
            "📤 رفع المنتج", 
            "✏️ تعديل البيانات",
            "🗑️ حذف آخر صورة", 
            "❌ إلغاء المنتج",
            "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم/رمز المنتج الفعلي وليس أحد الأزرار!\n\n🆔 مثال: 25748 أو ABC123");
            return;
        }
        
        if (empty(trim($text))) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم/رمز منتج صالح:");
            return;
        }
        
        $productId = trim($text);
        $usersData[$userId]["product_id"] = $productId;
        $usersData[$userId]["step"] = "sku";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ رقم/رمز المنتج: $productId\n🏷️ الرجاء إدخال رمز SKU (أرقام أو أحرف، مثل: SKU-25748 أو 25748):");
        writeLog("Set step to SKU for user $userId - waiting for SKU input");
        return;
    }
    
    if (isset($usersData[$userId]["step"]) && $usersData[$userId]["step"] === "sku") {
        // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
            "📦 إضافة منتج جديد",
            "📋 عرض البيانات الحالية",
            "📤 رفع المنتج",
            "✏️ تعديل البيانات",
            "🗑️ حذف آخر صورة",
            "❌ إلغاء المنتج",
            "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رمز SKU الفعلي وليس أحد الأزرار!\n\n🏷️ مثال: SKU-25748 أو 25748 أو ABC123");
            return;
        }
        
        if (empty(trim($text))) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رمز SKU (أرقام أو أحرف):");
            return;
        }
        
        $sku = trim($text);
        $usersData[$userId]["sku"] = $sku;
        $usersData[$userId]["step"] = "barcode";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ رمز SKU: $sku\n📊 الرجاء إدخال الباركود (مثال: 1234567890123):");
        return;
    }
    
    if (isset($usersData[$userId]["step"]) && $usersData[$userId]["step"] === "barcode") {
        // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
            "📦 إضافة منتج جديد", 
            "📋 عرض البيانات الحالية", 
            "📤 رفع المنتج", 
            "✏️ تعديل البيانات",
            "🗑️ حذف آخر صورة", 
            "❌ إلغاء المنتج",
            "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال الباركود الفعلي وليس أحد الأزرار!\n\n📊 مثال: 1234567890123");
            return;
        }
        
        if (empty(trim($text))) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال باركود صالح:");
            return;
        }
        
        $barcode = trim($text);
        $usersData[$userId]["barcode"] = $barcode;
        $usersData[$userId]["step"] = "price";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ الباركود: $barcode\n💰 الرجاء إدخال السعر:");
        return;
    }
    
    if ($usersData[$userId]["step"] === "price") {
        // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
            "📦 إضافة منتج جديد", 
            "📋 عرض البيانات الحالية", 
            "📤 رفع المنتج", 
            "✏️ تعديل البيانات",
            "🗑️ حذف آخر صورة", 
            "❌ إلغاء المنتج",
            "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال السعر الفعلي وليس أحد الأزرار!\n\n💰 مثال: 3040");
            return;
        }
        
        if (!is_numeric($text) || $text <= 0) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال سعر صالح:");
            return;
        }
        $usersData[$userId]["product"]["price"] = $text;
        $usersData[$userId]["step"] = "stock_quantity";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ السعر: $text\n📦 الرجاء إدخال كمية المخزون:");
        return;
    }
    
    if ($usersData[$userId]["step"] === "stock_quantity") {
        if (!is_numeric($text) || $text < 0) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال كمية صحيحة:");
            return;
        }
        $usersData[$userId]["stock_quantity"] = intval($text);
        $usersData[$userId]["step"] = "categories";
        saveData($usersData, $dataFile);
        sendTelegramMessage($chatId, "✅ تم حفظ المخزون: $text\n📂 الرجاء إدخال المجموعات (مثال: كهرمان، خرز، إكسسوارات):\n\n💡 اكتب المجموعات مفصولة بفواصل، مثال:\nكهرمان، خرز، إكسسوارات");
        return;
    }
        
    if ($usersData[$userId]["step"] === "categories") {
        // تجاهل أزرار البوت والأوامر
        if (in_array($text, [
            "📦 إضافة منتج جديد", 
            "📋 عرض البيانات الحالية", 
            "📤 رفع المنتج", 
            "✏️ تعديل البيانات",
            "🗑️ حذف آخر صورة", 
            "❌ إلغاء المنتج",
            "/new", "/start", "/show", "/upload", "/cancel"
        ])) {
            sendTelegramMessage($chatId, "⚠️ الرجاء إدخال المجموعات الفعلية وليس أحد الأزرار!\n\n📂 مثال: كهرمان، خرز، إكسسوارات");
            return;
        }
        
        // جلب الفئات من الموقع
        $categories = getWordPressCategories();
        if (!empty($categories)) {
            // عرض الفئات الرئيسية كأزرار
            $categoryButtons = [];
            $currentRow = [];
            
            foreach ($categories as $category) {
                $currentRow[] = $category['name'];
                
                if (count($currentRow) == 2) {
                    $categoryButtons[] = $currentRow;
                    $currentRow = [];
                }
            }
            
            if (!empty($currentRow)) {
                $categoryButtons[] = $currentRow;
            }
            
            
            $replyMarkup = [
                'keyboard' => $categoryButtons,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            
            // حفظ البيانات المؤقتة
            $usersData[$userId]["available_categories"] = $categories;
            $usersData[$userId]["step"] = "category_selection";
            saveData($usersData, $dataFile);
            
            writeLog("Displaying main categories keyboard with " . count($categoryButtons) . " rows");
            writeLog("Main categories keyboard: " . json_encode($categoryButtons));
            
            // إرسال رسالة منفصلة أولاً
            sendTelegramMessage($chatId, "✅ تم حفظ المخزون: $text");
            
            // ثم إرسال الأزرار
                                sendTelegramKeyboard($chatId, "📂 اختر الفئة الرئيسية من القائمة أدناه (فقط الفئات الرئيسية):", $replyMarkup);
        } else {
            // إذا لم يتم العثور على فئات، استخدم الطريقة العادية
        $categories = array_map('trim', explode(',', $text));
        $usersData[$userId]["categories"] = $categories;
        $usersData[$userId]["step"] = "long_description";
        saveData($usersData, $dataFile);
            sendTelegramMessage($chatId, "✅ تم حفظ المخزون: $text\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
    }
            return;
        }
        
    if ($usersData[$userId]["step"] === "long_description") {
        $usersData[$userId]["long_description"] = $text;
        $usersData[$userId]["step"] = "additional_info";
        saveData($usersData, $dataFile);
        // جلب خصائص المنتجات من WordPress وعرضها كأزرار
        $productAttributes = getWordPressProductAttributes();
        $supportedAttributes = ['النوع', 'عدد الخرز', 'الوزن', 'نوع القصة', 'اسم الخراط', 'نوع التطعيم'];
        $availableAttributes = [];
        
        // تسجيل الخصائص المتاحة للتشخيص
        writeLog("Available attributes from WordPress: " . json_encode(array_column($productAttributes, 'name')));
        
        foreach ($supportedAttributes as $attributeName) {
            foreach ($productAttributes as $attribute) {
                if ($attribute['name'] === $attributeName) {
                    $availableAttributes[] = $attributeName;
                    writeLog("Found supported attribute: " . $attributeName);
                    break;
                }
            }
        }
        
        writeLog("Final available attributes: " . json_encode($availableAttributes));
        
        if (!empty($availableAttributes)) {
            // عرض الخصائص المتاحة كأزرار مع علامات الإكمال
            $attributeButtons = [];
            $currentRow = [];
            
            foreach ($availableAttributes as $attributeName) {
                // التحقق من وجود القيمة المختارة
                $isCompleted = false;
                if (!empty($usersData[$userId]["current_additional_info"]) && 
                    isset($usersData[$userId]["current_additional_info"][$attributeName])) {
                    $isCompleted = true;
                }
                
                // إضافة علامة صح إذا تم الإكمال
                $buttonText = $isCompleted ? "✅ $attributeName" : $attributeName;
                $currentRow[] = $buttonText;
                
                if (count($currentRow) == 2) {
                    $attributeButtons[] = $currentRow;
                    $currentRow = [];
                }
            }
            
            // إضافة الصف الأخير إذا كان يحتوي على عناصر
            if (!empty($currentRow)) {
                $attributeButtons[] = $currentRow;
            }
            
            // إضافة زر "القياس" و "تم الاختيار" وإتاحة تخطي المعلومات الإضافية فقط
            $attributeButtons[] = ['القياس', '✅ تم الاختيار'];
            $attributeButtons[] = ['⏭️ تخطي المعلومات الإضافية'];
            
            $replyMarkup = [
                'keyboard' => $attributeButtons,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            
            // حفظ البيانات المؤقتة
            $usersData[$userId]["current_additional_info"] = [];
            $usersData[$userId]["available_attributes"] = $availableAttributes;
            $usersData[$userId]["product_attributes"] = $productAttributes;
        saveData($usersData, $dataFile);
            
            sendTelegramKeyboard($chatId, "✅ تم حفظ الوصف الطويل\n\n📋 اختر الخصائص المتاحة من القائمة أدناه:\n\n💡 يمكنك اختيار:\n• النوع\n• عدد الخرز\n• الوزن\n• نوع القصة\n• اسم الخراط\n• نوع التطعيم\n• القياس (إدخال يدوي)\n\nأو اضغط '⏭️ تخطي المعلومات الإضافية' للمتابعة", $replyMarkup);
        } else {
            // إذا لم يتم العثور على خصائص، استخدم الطريقة العادية
        sendTelegramMessage($chatId, "✅ تم حفظ الوصف الطويل\nℹ️ الرجاء إدخال المعلومات الإضافية:\n\n💡 اكتب كل معلومة في سطر منفصل:\nالنوع: كهرمان كالينقرادي سوبر\nعدد الخرز: 67 خرزة\nالقياس: 8.5 - 11.5 مم\nالوزن: 36 غ");
        }
        return;
    }
    
    // معالجة اختيار الخصائص من الأزرار
    if (isset($usersData[$userId]["waiting_for_attribute_selection"]) && $usersData[$userId]["waiting_for_attribute_selection"]) {
        if ($text === "✅ تم الاختيار") {
            // إنهاء اختيار الخاصية والانتقال للخطوة التالية
            if ($usersData[$userId]["edit_mode"] === "editadditionalinfo") {
                // في وضع التعديل
                $usersData[$userId]["edit_mode"] = null;
                unset($usersData[$userId]["waiting_for_attribute_selection"]);
                unset($usersData[$userId]["current_attribute_name"]);
                unset($usersData[$userId]["available_attribute_terms"]);
                saveData($usersData, $dataFile);
                
                $infoText = "";
                foreach ($usersData[$userId]["current_additional_info"] as $key => $value) {
                    $infoText .= "• $key: $value\n";
                }
                
                sendTelegramMessage($chatId, "✅ تم تحديث المعلومات الإضافية بنجاح:\n$infoText");
                showMainKeyboard($chatId);
            } else {
                // في الوضع العادي
                $usersData[$userId]["step"] = "images";
                unset($usersData[$userId]["waiting_for_attribute_selection"]);
                unset($usersData[$userId]["current_attribute_name"]);
                unset($usersData[$userId]["available_attribute_terms"]);
        saveData($usersData, $dataFile);
                
                $infoText = "";
                foreach ($usersData[$userId]["current_additional_info"] as $key => $value) {
                    $infoText .= "• $key: $value\n";
                }
                
                sendTelegramMessage($chatId, "✅ تم حفظ المعلومات الإضافية:\n$infoText\n\n📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n\n💡 استخدم زر '📋 عرض البيانات الحالية' لمراجعة جميع البيانات قبل الرفع.");
                
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
                
                sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
            }
            return;
        } else {
            // اختيار قيمة من القائمة
            $selectedValue = null;
            foreach ($usersData[$userId]["available_attribute_terms"] as $term) {
                if ($term['name'] === $text) {
                    $selectedValue = $term['name'];
                    break;
                }
            }
            
            if ($selectedValue) {
                // حفظ القيمة المختارة
                $attributeName = $usersData[$userId]["current_attribute_name"];
                $usersData[$userId]["current_additional_info"][$attributeName] = $selectedValue;
        saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم اختيار $attributeName: $selectedValue\n\n💡 اضغط على '✅ تم الاختيار' للمتابعة أو اختر قيمة أخرى.");
            return;
            }
        }
    }
    
    // معالجة اختيار الخصائص من الأزرار في مرحلة المعلومات الإضافية
    if ($usersData[$userId]["step"] === "additional_info" && isset($usersData[$userId]["available_attributes"])) {
        $supportedAttributes = ['النوع', 'عدد الخرز', 'الوزن', 'نوع القصة', 'اسم الخراط', 'نوع التطعيم'];
        
        if (in_array($text, $supportedAttributes)) {
            // اختيار خاصية من القائمة
            $attributeName = $text;
            
            // البحث عن الخاصية في البيانات المحفوظة
            $targetAttribute = null;
            foreach ($usersData[$userId]["product_attributes"] as $attribute) {
                if ($attribute['name'] === $attributeName) {
                    $targetAttribute = $attribute;
                    break;
                }
            }
            
            if ($targetAttribute) {
                // جلب قيم الخاصية
                $attributeTerms = getAttributeTerms($targetAttribute['id']);
                
                if (!empty($attributeTerms)) {
                    // عرض قيم الخاصية كأزرار
                    $valueButtons = [];
                    $currentRow = [];
                    
                    // تنظيم الأزرار بشكل أفضل
                    $totalTerms = count($attributeTerms);
                    $termsPerRow = $totalTerms > 10 ? 3 : 2; // 3 أزرار في الصف إذا كان هناك أكثر من 10 قيم
                    
                    foreach ($attributeTerms as $term) {
                        $currentRow[] = $term['name'];
                        
                        if (count($currentRow) == $termsPerRow) {
                            $valueButtons[] = $currentRow;
                            $currentRow = [];
                        }
                    }
                    
                    // إضافة الصف الأخير إذا كان يحتوي على عناصر
                    if (!empty($currentRow)) {
                        $valueButtons[] = $currentRow;
                    }
                    
                    // إضافة زر "رجوع"
                    $valueButtons[] = ['⬅️ رجوع'];
                    
                    $replyMarkup = [
                        'keyboard' => $valueButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                    // حفظ البيانات المؤقتة
                    $usersData[$userId]["waiting_for_value_selection"] = true;
                    $usersData[$userId]["current_attribute_name"] = $attributeName;
                    $usersData[$userId]["available_attribute_terms"] = $attributeTerms;
                    saveData($usersData, $dataFile);
                    
                    $totalTerms = count($attributeTerms);
                    sendTelegramKeyboard($chatId, "📋 اختر $attributeName من القائمة أدناه:\n\n📊 تم جلب $totalTerms قيمة من الموقع", $replyMarkup);
                    return;
                }
            }
        } elseif ($text === "✅ تم الاختيار") {
            // إنهاء اختيار الخصائص والانتقال للخطوة التالية
        $usersData[$userId]["step"] = "images";
            
            // حفظ المعلومات الإضافية المختارة في additional_info
            if (!empty($usersData[$userId]["current_additional_info"])) {
                $usersData[$userId]["additional_info"] = $usersData[$userId]["current_additional_info"];
            }
            
            unset($usersData[$userId]["available_attributes"]);
            unset($usersData[$userId]["product_attributes"]);
            unset($usersData[$userId]["current_additional_info"]);
        saveData($usersData, $dataFile);
        
        $infoText = "";
            foreach ($usersData[$userId]["current_additional_info"] as $key => $value) {
            $infoText .= "• $key: $value\n";
        }
            
            if (empty($infoText)) {
                $infoText = "لا توجد معلومات إضافية";
        }
        
        sendTelegramMessage($chatId, "✅ تم حفظ المعلومات الإضافية:\n$infoText\n\n📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n\n💡 استخدم زر '📋 عرض البيانات الحالية' لمراجعة جميع البيانات قبل الرفع.");
        
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
        
        sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
        return;
        } elseif ($text === "القياس") {
            // بدء إدخال القياس
            $usersData[$userId]["waiting_for_measurement"] = true;
            $usersData[$userId]["measurement_step"] = "type";
            saveData($usersData, $dataFile);
            
            // عرض خيارات نوع القياس
            $measurementKeyboard = [
                ['🔵 دائري (قطر واحد)', '📐 مستطيل (طول وعرض)']
            ];
            
            $replyMarkup = [
                'keyboard' => $measurementKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            
            sendTelegramKeyboard($chatId, "📏 اختر نوع القياس:", $replyMarkup);
            return;
        } elseif ($text === "➕ إضافة معلومات أخرى") {
            // الانتقال لوضع إدخال المعلومات يدوياً
            unset($usersData[$userId]["available_attributes"]);
            unset($usersData[$userId]["product_attributes"]);
            saveData($usersData, $dataFile);
            
            sendTelegramMessage($chatId, "📝 الرجاء إدخال المعلومات الإضافية يدوياً:\n\n💡 اكتب كل معلومة في سطر منفصل:\nالقياس: 8.5 - 11.5 مم\nالمادة: كهرمان طبيعي\nاللون: بني");
            return;
        } elseif ($text === "⏭️ تخطي المعلومات الإضافية") {
            // تخطي المعلومات الإضافية والانتقال للصور
            $usersData[$userId]["step"] = "images";
            unset($usersData[$userId]["available_attributes"]);
            unset($usersData[$userId]["product_attributes"]);
            saveData($usersData, $dataFile);
            
            sendTelegramMessage($chatId, "⏭️ تم تخطي المعلومات الإضافية\n\n📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n\n💡 استخدم زر '📋 عرض البيانات الحالية' لمراجعة جميع البيانات قبل الرفع.");
        
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
        
        sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
        return;
        }
    }
    
    // معالجة اختيار قيم الخصائص
    if (isset($usersData[$userId]["waiting_for_value_selection"]) && $usersData[$userId]["waiting_for_value_selection"]) {
        if ($text === "⬅️ رجوع") {
            // العودة لقائمة الخصائص
            unset($usersData[$userId]["waiting_for_value_selection"]);
            unset($usersData[$userId]["current_attribute_name"]);
            unset($usersData[$userId]["available_attribute_terms"]);
            saveData($usersData, $dataFile);
            
            // إعادة عرض أزرار الخصائص مع علامات الإكمال
            $attributeButtons = [];
            $currentRow = [];
            
            foreach ($usersData[$userId]["available_attributes"] as $attributeName) {
                // التحقق من وجود القيمة المختارة
                $isCompleted = false;
                if (!empty($usersData[$userId]["current_additional_info"]) && 
                    isset($usersData[$userId]["current_additional_info"][$attributeName])) {
                    $isCompleted = true;
                }
                
                // إضافة علامة صح إذا تم الإكمال
                $buttonText = $isCompleted ? "✅ $attributeName" : $attributeName;
                $currentRow[] = $buttonText;
                
                if (count($currentRow) == 2) {
                    $attributeButtons[] = $currentRow;
                    $currentRow = [];
                }
            }
            
            if (!empty($currentRow)) {
                $attributeButtons[] = $currentRow;
            }
            
            // التحقق من وجود القياس
            $measurementCompleted = false;
            if (!empty($usersData[$userId]["current_additional_info"]) && 
                isset($usersData[$userId]["current_additional_info"]["القياس"])) {
                $measurementCompleted = true;
            }
            
            $measurementButton = $measurementCompleted ? "✅ القياس" : "القياس";
            $attributeButtons[] = [$measurementButton, '✅ تم الاختيار'];
            $attributeButtons[] = ['⏭️ تخطي المعلومات الإضافية'];
            
            $replyMarkup = [
                'keyboard' => $attributeButtons,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            
            sendTelegramKeyboard($chatId, "📋 اختر الخصائص المتاحة من القائمة أدناه:", $replyMarkup);
            return;
        } else {
            // اختيار قيمة من القائمة
            $selectedValue = null;
            foreach ($usersData[$userId]["available_attribute_terms"] as $term) {
                if ($term['name'] === $text) {
                    $selectedValue = $term['name'];
                    break;
                }
            }
            
            if ($selectedValue) {
                // حفظ القيمة المختارة
                $attributeName = $usersData[$userId]["current_attribute_name"];
                $usersData[$userId]["current_additional_info"][$attributeName] = $selectedValue;
                saveData($usersData, $dataFile);
                
                // العودة لقائمة الخصائص
                unset($usersData[$userId]["waiting_for_value_selection"]);
                unset($usersData[$userId]["current_attribute_name"]);
                unset($usersData[$userId]["available_attribute_terms"]);
                saveData($usersData, $dataFile);
                
                // إعادة عرض أزرار الخصائص مع علامات الإكمال
                $attributeButtons = [];
                $currentRow = [];
                
                foreach ($usersData[$userId]["available_attributes"] as $attrName) {
                    // التحقق من وجود القيمة المختارة
                    $isCompleted = false;
                    if (!empty($usersData[$userId]["current_additional_info"]) && 
                        isset($usersData[$userId]["current_additional_info"][$attrName])) {
                        $isCompleted = true;
                    }
                    
                    // إضافة علامة صح إذا تم الإكمال
                    $buttonText = $isCompleted ? "✅ $attrName" : $attrName;
                    $currentRow[] = $buttonText;
                    
                    if (count($currentRow) == 2) {
                        $attributeButtons[] = $currentRow;
                        $currentRow = [];
                    }
                }
                
                if (!empty($currentRow)) {
                    $attributeButtons[] = $currentRow;
                }
                
                // التحقق من وجود القياس
                $measurementCompleted = false;
                if (!empty($usersData[$userId]["current_additional_info"]) && 
                    isset($usersData[$userId]["current_additional_info"]["القياس"])) {
                    $measurementCompleted = true;
                }
                
                $measurementButton = $measurementCompleted ? "✅ القياس" : "القياس";
                $attributeButtons[] = [$measurementButton, '✅ تم الاختيار'];
                
                $replyMarkup = [
                    'keyboard' => $attributeButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramMessage($chatId, "✅ تم اختيار $attributeName: $selectedValue");
                sendTelegramKeyboard($chatId, "📋 اختر الخصائص المتاحة من القائمة أدناه:", $replyMarkup);
                return;
            }
        }
    }
    
    // معالجة إدخال القياس
    if (isset($usersData[$userId]["waiting_for_measurement"]) && $usersData[$userId]["waiting_for_measurement"]) {
        if ($usersData[$userId]["measurement_step"] === "type") {
            // اختيار نوع القياس
            if ($text === "🔵 دائري (قطر واحد)") {
                $usersData[$userId]["measurement_type"] = "circular";
                $usersData[$userId]["measurement_step"] = "diameter";
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "🔵 تم اختيار القياس الدائري\n\n📏 الرجاء إدخال القطر (مثال: 8.5):");
                return;
            } elseif ($text === "📐 مستطيل (طول وعرض)") {
                $usersData[$userId]["measurement_type"] = "rectangular";
                $usersData[$userId]["measurement_step"] = "length";
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "📐 تم اختيار القياس المستطيل\n\n📏 الرجاء إدخال الطول (مثال: 8.5):");
                return;
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء اختيار نوع القياس من الأزرار المتاحة.");
                return;
            }
        } elseif ($usersData[$userId]["measurement_step"] === "diameter") {
            // إدخال القطر للقياس الدائري
            if (is_numeric($text)) {
                $diameter = $text;
                
                // حفظ القياس الدائري
                $usersData[$userId]["current_additional_info"]["القياس"] = "قطر $diameter مم";
                
                // إزالة البيانات المؤقتة
                unset($usersData[$userId]["waiting_for_measurement"]);
                unset($usersData[$userId]["measurement_step"]);
                unset($usersData[$userId]["measurement_type"]);
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم حفظ القياس الدائري: قطر $diameter مم");
                
                // إعادة عرض أزرار الخصائص مع علامات الإكمال
                $attributeButtons = [];
                $currentRow = [];
                
                foreach ($usersData[$userId]["available_attributes"] as $attributeName) {
                    // التحقق من وجود القيمة المختارة
                    $isCompleted = false;
                    if (!empty($usersData[$userId]["current_additional_info"]) && 
                        isset($usersData[$userId]["current_additional_info"][$attributeName])) {
                        $isCompleted = true;
                    }
                    
                    // إضافة علامة صح إذا تم الإكمال
                    $buttonText = $isCompleted ? "✅ $attributeName" : $attributeName;
                    $currentRow[] = $buttonText;
                    
                    if (count($currentRow) == 2) {
                        $attributeButtons[] = $currentRow;
                        $currentRow = [];
                    }
                }
                
                if (!empty($currentRow)) {
                    $attributeButtons[] = $currentRow;
                }
                
                // التحقق من وجود القياس
                $measurementCompleted = false;
                if (!empty($usersData[$userId]["current_additional_info"]) && 
                    isset($usersData[$userId]["current_additional_info"]["القياس"])) {
                    $measurementCompleted = true;
                }
                
                $measurementButton = $measurementCompleted ? "✅ القياس" : "القياس";
                $attributeButtons[] = [$measurementButton, '✅ تم الاختيار'];
                
                $replyMarkup = [
                    'keyboard' => $attributeButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "📋 اختر الخصائص المتاحة من القائمة أدناه:", $replyMarkup);
                return;
        } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم صحيح للقطر:");
                return;
            }
        } elseif ($usersData[$userId]["measurement_step"] === "length") {
            // إدخال الطول للقياس المستطيل
            if (is_numeric($text)) {
                $usersData[$userId]["measurement_length"] = $text;
                $usersData[$userId]["measurement_step"] = "width";
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم حفظ الطول: $text\n\n📏 الرجاء إدخال العرض (مثال: 11.5):");
                return;
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم صحيح للطول:");
                return;
            }
        } elseif ($usersData[$userId]["measurement_step"] === "width") {
            // إدخال العرض للقياس المستطيل
            if (is_numeric($text)) {
                $length = $usersData[$userId]["measurement_length"];
                $width = $text;
                
                // حفظ القياس المستطيل كاملاً
                $usersData[$userId]["current_additional_info"]["القياس"] = "$length - $width مم";
                
                // إزالة البيانات المؤقتة
                unset($usersData[$userId]["waiting_for_measurement"]);
                unset($usersData[$userId]["measurement_step"]);
                unset($usersData[$userId]["measurement_type"]);
                unset($usersData[$userId]["measurement_length"]);
                saveData($usersData, $dataFile);
                
                sendTelegramMessage($chatId, "✅ تم حفظ القياس المستطيل: $length - $width مم");
                
                // إعادة عرض أزرار الخصائص مع علامات الإكمال
                $attributeButtons = [];
                $currentRow = [];
                
                foreach ($usersData[$userId]["available_attributes"] as $attributeName) {
                    // التحقق من وجود القيمة المختارة
                    $isCompleted = false;
                    if (!empty($usersData[$userId]["current_additional_info"]) && 
                        isset($usersData[$userId]["current_additional_info"][$attributeName])) {
                        $isCompleted = true;
                    }
                    
                    // إضافة علامة صح إذا تم الإكمال
                    $buttonText = $isCompleted ? "✅ $attributeName" : $attributeName;
                    $currentRow[] = $buttonText;
                    
                    if (count($currentRow) == 2) {
                        $attributeButtons[] = $currentRow;
                        $currentRow = [];
                    }
                }
                
                if (!empty($currentRow)) {
                    $attributeButtons[] = $currentRow;
                }
                
                // التحقق من وجود القياس
                $measurementCompleted = false;
                if (!empty($usersData[$userId]["current_additional_info"]) && 
                    isset($usersData[$userId]["current_additional_info"]["القياس"])) {
                    $measurementCompleted = true;
                }
                
                $measurementButton = $measurementCompleted ? "✅ القياس" : "القياس";
                $attributeButtons[] = [$measurementButton, '✅ تم الاختيار'];
                $attributeButtons[] = ['➕ إضافة معلومات أخرى'];
                
                $replyMarkup = [
                    'keyboard' => $attributeButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                sendTelegramKeyboard($chatId, "📋 اختر الخصائص المتاحة من القائمة أدناه:", $replyMarkup);
                return;
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم صحيح للعرض:");
                return;
            }
        }
    }
    
    // معالجة اختيار الفئات
    if ($usersData[$userId]["step"] === "category_selection") {
        writeLog("User is in category_selection step");
        writeLog("User input: " . $text);
        writeLog("Available categories: " . json_encode(array_column($usersData[$userId]["available_categories"], 'name')));
        
        // اختيار فئة من القائمة
        $selectedCategory = null;
        foreach ($usersData[$userId]["available_categories"] as $category) {
            if ($category['name'] === $text) {
                $selectedCategory = $category;
                break;
            }
        }
        
        if ($selectedCategory) {
            writeLog("User selected category: " . $selectedCategory['name'] . " (ID: " . $selectedCategory['id'] . ")");
            // حفظ الفئة المختارة
            $usersData[$userId]["selected_categories"] = [$selectedCategory];
            
            // جلب الفئات الفرعية
            writeLog("Fetching sub-categories for category ID: " . $selectedCategory['id'] . " (Name: " . $selectedCategory['name'] . ")");
            $subCategories = getWordPressSubCategories($selectedCategory['id']);
            writeLog("Found " . count($subCategories) . " sub-categories for category: " . $selectedCategory['name']);
            
            if (!empty($subCategories)) {
                // عرض الفئات الفرعية كأزرار
                $subCategoryButtons = [];
                $currentRow = [];
                
                foreach ($subCategories as $subCategory) {
                    $currentRow[] = $subCategory['name'];
                    
                    if (count($currentRow) == 2) {
                        $subCategoryButtons[] = $currentRow;
                        $currentRow = [];
                    }
                }
                
                if (!empty($currentRow)) {
                    $subCategoryButtons[] = $currentRow;
                }
                
                
                // إضافة زر تخطي الفئة الفرعية لجعل الاختيار اختيارياً
                $subCategoryButtons[] = ['⏭️ تخطي الفئة الفرعية'];

                $replyMarkup = [
                    'keyboard' => $subCategoryButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                // حفظ البيانات المؤقتة
                $usersData[$userId]["available_sub_categories"] = $subCategories;
                $usersData[$userId]["step"] = "sub_category_selection";
                saveData($usersData, $dataFile);
                
                writeLog("Displaying sub-categories keyboard with " . count($subCategoryButtons) . " rows");
                writeLog("Sub-categories keyboard: " . json_encode($subCategoryButtons));
                
                // إرسال رسالة منفصلة أولاً
                sendTelegramMessage($chatId, "✅ تم اختيار الفئة: " . $selectedCategory['name']);
                
                // ثم إرسال الأزرار
                sendTelegramKeyboard($chatId, "📂 اختر الفئة الفرعية من القائمة أدناه:", $replyMarkup);
            } else {
                // لا توجد فئات فرعية، الانتقال للخطوة التالية
                writeLog("No sub-categories found for category: " . $selectedCategory['name'] . ", proceeding to next step");
                $usersData[$userId]["step"] = "long_description";
                saveData($usersData, $dataFile);
                
                $categoryNames = array_column($usersData[$userId]["selected_categories"], 'name');
                sendTelegramMessage($chatId, "✅ تم حفظ الفئات: " . implode(', ', $categoryNames) . "\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
            }
            return;
        }
    }
    
    // معالجة اختيار الفئات الفرعية
    if ($usersData[$userId]["step"] === "sub_category_selection") {
        writeLog("User is in sub_category_selection step");
        writeLog("User input: " . $text);
        writeLog("Available sub-categories: " . json_encode(array_column($usersData[$userId]["available_sub_categories"], 'name')));
        
        // تم الضغط على زر تخطي الفئة الفرعية
        if ($text === "⏭️ تخطي الفئة الفرعية") {
            $usersData[$userId]["step"] = "long_description";
            saveData($usersData, $dataFile);
            
            $categoryNames = array_column($usersData[$userId]["selected_categories"], 'name');
            sendTelegramMessage($chatId, "⏭️ تم تخطي الفئة الفرعية\n✅ تم حفظ الفئات: " . implode(' > ', $categoryNames) . "\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
            return;
        }

        // اختيار فئة فرعية من القائمة
        $selectedSubCategory = null;
        foreach ($usersData[$userId]["available_sub_categories"] as $subCategory) {
            if ($subCategory['name'] === $text) {
                $selectedSubCategory = $subCategory;
                break;
            }
        }
        
        if ($selectedSubCategory) {
            // إضافة الفئة الفرعية للفئات المختارة
            $usersData[$userId]["selected_categories"][] = $selectedSubCategory;
            writeLog("Added sub-category to selection: " . $selectedSubCategory['name'] . " (ID: " . $selectedSubCategory['id'] . ")");
            
            // جلب الفئات الفرعية للفئة الفرعية (sub-sub-categories)
            writeLog("Fetching sub-sub-categories for sub-category ID: " . $selectedSubCategory['id'] . " (Name: " . $selectedSubCategory['name'] . ")");
            $subSubCategories = getWordPressSubCategories($selectedSubCategory['id']);
            writeLog("Found " . count($subSubCategories) . " sub-sub-categories for sub-category: " . $selectedSubCategory['name']);
            
            if (!empty($subSubCategories)) {
                writeLog("Sub-sub-categories found: " . json_encode(array_column($subSubCategories, 'name')));
                // عرض الفئات الفرعية للفئة الفرعية كأزرار
                $subSubCategoryButtons = [];
                $currentRow = [];
                
                foreach ($subSubCategories as $subSubCategory) {
                    $currentRow[] = $subSubCategory['name'];
                    
                    if (count($currentRow) == 2) {
                        $subSubCategoryButtons[] = $currentRow;
                        $currentRow = [];
                    }
                }
                
                if (!empty($currentRow)) {
                    $subSubCategoryButtons[] = $currentRow;
                }
                
                
                // إضافة زر تخطي الفئة الفرعية لجعل الاختيار اختيارياً
                $subSubCategoryButtons[] = ['⏭️ تخطي الفئة الفرعية'];

                $replyMarkup = [
                    'keyboard' => $subSubCategoryButtons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ];
                
                writeLog("Created sub-sub-category keyboard with " . count($subSubCategoryButtons) . " rows");
                writeLog("Sub-sub-category keyboard: " . json_encode($subSubCategoryButtons));
                
                // حفظ البيانات المؤقتة
                $usersData[$userId]["available_sub_sub_categories"] = $subSubCategories;
                $usersData[$userId]["step"] = "sub_sub_category_selection";
                saveData($usersData, $dataFile);
                
                // إرسال رسالة منفصلة أولاً
                sendTelegramMessage($chatId, "✅ تم اختيار الفئة الفرعية: " . $selectedSubCategory['name']);
                
                // ثم إرسال الأزرار
                sendTelegramKeyboard($chatId, "📂 اختر الفئة الفرعية للفئة الفرعية من القائمة أدناه:", $replyMarkup);
            } else {
                // لا توجد فئات فرعية للفئة الفرعية، الانتقال للخطوة التالية
                writeLog("No sub-sub-categories found for sub-category: " . $selectedSubCategory['name'] . ", proceeding to next step");
                writeLog("Selected categories so far: " . json_encode(array_column($usersData[$userId]["selected_categories"], 'name')));
                $usersData[$userId]["step"] = "long_description";
                saveData($usersData, $dataFile);
                
                $categoryNames = array_column($usersData[$userId]["selected_categories"], 'name');
                sendTelegramMessage($chatId, "✅ تم حفظ الفئات: " . implode(' > ', $categoryNames) . "\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
            }
            return;
        }
    }
    
    // معالجة اختيار الفئات الفرعية للفئة الفرعية
    if ($usersData[$userId]["step"] === "sub_sub_category_selection") {
        writeLog("User is in sub_sub_category_selection step");
        writeLog("Available sub-sub-categories: " . json_encode(array_column($usersData[$userId]["available_sub_sub_categories"], 'name')));
        
        // تم الضغط على زر تخطي الفئة الفرعية
        if ($text === "⏭️ تخطي الفئة الفرعية") {
            $usersData[$userId]["step"] = "long_description";
            saveData($usersData, $dataFile);
            
            $categoryNames = array_column($usersData[$userId]["selected_categories"], 'name');
            sendTelegramMessage($chatId, "⏭️ تم تخطي الفئة الفرعية\n✅ تم حفظ الفئات: " . implode(' > ', $categoryNames) . "\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
            return;
        }

        // اختيار فئة فرعية للفئة الفرعية من القائمة
        $selectedSubSubCategory = null;
        foreach ($usersData[$userId]["available_sub_sub_categories"] as $subSubCategory) {
            if ($subSubCategory['name'] === $text) {
                $selectedSubSubCategory = $subSubCategory;
                break;
            }
        }
        
        if ($selectedSubSubCategory) {
            // إضافة الفئة الفرعية للفئة الفرعية للفئات المختارة
            $usersData[$userId]["selected_categories"][] = $selectedSubSubCategory;
            
            // الانتقال للخطوة التالية
            $usersData[$userId]["step"] = "long_description";
            saveData($usersData, $dataFile);
            
            $categoryNames = array_column($usersData[$userId]["selected_categories"], 'name');
            sendTelegramMessage($chatId, "✅ تم حفظ الفئات: " . implode(', ', $categoryNames) . "\n📖 الرجاء إدخال الوصف الطويل للمنتج (مثال: سلسلة كهرمان طبيعية من أجود أنواع الكهرمان...):");
            return;
        }
    }
    }
    
    if ($usersData[$userId]["step"] === "additional_info") {
        // التحقق من الخصائص المدعومة
        $supportedAttributes = ['النوع', 'عدد الخرز', 'الوزن', 'نوع القصة', 'اسم الخراط', 'نوع التطعيم'];
        $attributeFound = false;
        
        foreach ($supportedAttributes as $attributeName) {
            if (preg_match('/^' . preg_quote($attributeName) . ':\s*(.*)$/', $text, $matches)) {
                $attributeFound = true;
                
                // جلب خصائص المنتجات من WordPress
                $productAttributes = getWordPressProductAttributes();
                
                // البحث عن الخاصية المطلوبة
                $targetAttribute = null;
                foreach ($productAttributes as $attribute) {
                    if ($attribute['name'] === $attributeName) {
                        $targetAttribute = $attribute;
                        break;
                    }
                }
                
                if ($targetAttribute) {
                    // جلب قيم الخاصية
                    $attributeTerms = getAttributeTerms($targetAttribute['id']);
                    
                    if (!empty($attributeTerms)) {
                        // عرض قيم الخاصية كأزرار
                        $attributeButtons = [];
                        $currentRow = [];
                        
                        foreach ($attributeTerms as $term) {
                            $currentRow[] = $term['name'];
                            
                            if (count($currentRow) == 2) {
                                $attributeButtons[] = $currentRow;
                                $currentRow = [];
                            }
                        }
                        
                        // إضافة الصف الأخير إذا كان يحتوي على عناصر
                        if (!empty($currentRow)) {
                            $attributeButtons[] = $currentRow;
                        }
                        
                        // إضافة زر "تم الاختيار"
                        $attributeButtons[] = ['✅ تم الاختيار'];
                        
                        $replyMarkup = [
                            'keyboard' => $attributeButtons,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false
                        ];
                        
                        // حفظ البيانات المؤقتة
                        if (!isset($usersData[$userId]["current_additional_info"])) {
                            $usersData[$userId]["current_additional_info"] = [];
                        }
                        $usersData[$userId]["waiting_for_attribute_selection"] = true;
                        $usersData[$userId]["current_attribute_name"] = $attributeName;
                        $usersData[$userId]["available_attribute_terms"] = $attributeTerms;
                        saveData($usersData, $dataFile);
                        
                        sendTelegramKeyboard($chatId, "📋 اختر $attributeName من القائمة أدناه:", $replyMarkup);
                        return;
                    }
                }
                
                // إذا لم يتم العثور على القيم، أرسل رسالة خطأ
                sendTelegramMessage($chatId, "⚠️ لم يتم العثور على قيم لـ $attributeName في الموقع.\n\n💡 يمكنك كتابة القيمة يدوياً أو تجربة خاصية أخرى.");
                return;
            }
        }
        
        // إذا لم تكن أي من الخصائص المدعومة، استخدم الطريقة العادية
        $additionalInfo = parseAdditionalInfo($text);
        $usersData[$userId]["additional_info"] = $additionalInfo;
        $usersData[$userId]["step"] = "images";
        saveData($usersData, $dataFile);
        
        $infoText = "";
        foreach ($additionalInfo as $key => $value) {
            $infoText .= "• $key: $value\n";
        }
        
        sendTelegramMessage($chatId, "✅ تم حفظ المعلومات الإضافية:\n$infoText\n\n📸 يمكنك الآن إرسال صور المنتج (واحدة تلو الأخرى).\n\n💡 استخدم زر '📋 عرض البيانات الحالية' لمراجعة جميع البيانات قبل الرفع.");
        
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
        
        sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
        return;
    }

function uploadProduct($userData, $chatId) {
    try {
        global $woocommerceUrl, $consumerKey, $consumerSecret;
        writeLog("Starting simple product upload process...");

        // 1. التحقق من البيانات المطلوبة
        if (empty($userData["product"]["name"]) || 
            empty($userData["product"]["price"]) || 
            empty($userData["long_description"]) || 
            empty($userData["sku"]) ||
            empty($userData["images"])) {
            sendTelegramMessage($chatId, "❌ بيانات المنتج غير مكتملة (الاسم، السعر، الوصف الطويل، رمز SKU، وصورة واحدة على الأقل)");
            return false;
        }

        // 2. تحضير الصور
        writeLog("Preparing images. Total image IDs: " . count($userData["images"]));
        $allImages = [];
        $processedImageIds = [];
        
        foreach ($userData["images"] as $index => $imageId) {
            if (in_array($imageId, $processedImageIds)) {
                writeLog("Skipping duplicate image ID: " . $imageId);
                continue;
            }
            
            writeLog("Processing image " . ($index + 1) . " of " . count($userData["images"]) . " - file_id: " . $imageId);
            $imageUrl = getTelegramFileUrl($imageId);
            if ($imageUrl) {
                $allImages[] = ['src' => $imageUrl];
                $processedImageIds[] = $imageId;
                writeLog("✓ Image " . count($allImages) . " prepared successfully: " . $imageUrl);
            } else {
                writeLog("✗ Failed to get URL for image ID: " . $imageId);
            }
        }

        writeLog("Total images prepared: " . count($allImages) . " out of " . count($userData["images"]));
        
        if (empty($allImages)) {
            writeLog("ERROR: No images were prepared successfully!");
            sendTelegramMessage($chatId, "❌ فشل في تحضير الصور");
            return false;
        }

        // 3. بناء بيانات المنتج
        $productType = $userData["product_type"] ?? 'simple';
        $product = [
            'name' => $userData["product"]["name"],
            'type' => $productType,
            'status' => 'publish',
            'description' => $userData["long_description"],
            'short_description' => $userData["short_description"],
            'regular_price' => (string)$userData["product"]["price"],
            'manage_stock' => true,
            'stock_quantity' => $userData["stock_quantity"],
            'stock_status' => $userData["stock_quantity"] > 0 ? 'instock' : 'outofstock',
            'images' => $allImages,
            'meta_data' => []
        ];

        // إضافة رمز المنتج إذا كان موجوداً
        if (!empty($userData["sku"])) {
            $product['sku'] = $userData["sku"];
        }

        // إضافة رقم المنتج إذا كان موجوداً
        if (!empty($userData["product_id"])) {
            $product['meta_data'][] = [
                'key' => 'product_id',
                'value' => $userData["product_id"]
            ];
            
            // إنشاء slug للمنتج باستخدام رقم/رمز المنتج فقط
            $productId = $userData["product_id"];
            
            // تنظيف رقم/رمز المنتج وإزالة الأحرف الخاصة
            $cleanProductId = preg_replace('/[^\p{L}\p{N}-]/u', '', $productId);
            $cleanProductId = strtolower($cleanProductId);
            
            // إنشاء slug باستخدام رقم/رمز المنتج فقط
            $slug = $cleanProductId;
            
            // إضافة slug للمنتج
            $product['slug'] = $slug;
            
            writeLog("Generated product slug: " . $slug);
        }
        
        // إضافة الباركود إذا كان موجوداً
        if (!empty($userData["barcode"])) {
            $barcodeValue = (string)$userData["barcode"];
            // مفاتيح شائعة الاستخدام عبر إضافات مختلفة لعرض الباركود داخل لوحة التحكم
            $barcodeMetaKeys = [
                'barcode',            // عام
                '_barcode',           // بعض الثيمات/الإضافات
                'الباركود',           // مفتاح عربي شائع
                'upc',                // بعض الإضافات تعتبره UPC
                'ean',                // أو EAN
                'gtin',               // أو GTIN
                'product_barcode',
                '_product_barcode',
                // Barcode Scanner plugin field
                'usbs_barcode_field',
                '_usbs_barcode_field',
                'wf_barcode',
                '_wf_barcode',
                'alg_wc_pvbc_barcode',
                '_alg_wc_pvbc_barcode',
                'mwb_barcode',
                '_mwb_barcode',
                'bs_barcode',
                '_bs_barcode'
            ];
            foreach ($barcodeMetaKeys as $metaKey) {
                $product['meta_data'][] = [
                    'key' => $metaKey,
                    'value' => $barcodeValue
                ];
            }

            // مفاتيح GTIN/UPC/EAN شائعة (إضافات Google Feed وغيرها)
            $product['meta_data'][] = [
                'key' => '_wc_gpf_gtin',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => '_wc_gpf_upc',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => '_wc_gpf_ean',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => '_wc_gpf_isbn',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => '_wpm_gtin_code',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => 'wpm_gtin_code',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => '_alg_ean',
                'value' => $barcodeValue
            ];
            $product['meta_data'][] = [
                'key' => 'alg_ean',
                'value' => $barcodeValue
            ];

            // تخزين مصفوفة البيانات الخاصة ببعض الإضافات مثل Product Feed Pro
            $product['meta_data'][] = [
                'key' => '_woocommerce_gpf_data',
                'value' => [
                    'gtin' => $barcodeValue,
                    'upc' => $barcodeValue,
                    'ean' => $barcodeValue,
                    'isbn' => $barcodeValue
                ]
            ];

            // لا نضيفه كـ attribute حتى لا يظهر في صفحة المنتج
            writeLog("Stored barcode in meta only (hidden from product page attributes). Keys: " . json_encode($barcodeMetaKeys) . " => " . $barcodeValue);
        }

        // دمج المعلومات الإضافية العادية مع المعلومات الإضافية المختارة
        $allAdditionalInfo = $userData["additional_info"] ?? [];
        if (!empty($userData["current_additional_info"])) {
            $allAdditionalInfo = array_merge($allAdditionalInfo, $userData["current_additional_info"]);
        }

        // توليد الوصف القصير تلقائياً من المعلومات الإضافية
        $shortDescription = generateShortDescription($userData["product"]["name"], $allAdditionalInfo);
        $product['short_description'] = $shortDescription;

        // إضافة الفئات إذا كانت موجودة
        if (!empty($userData["selected_categories"])) {
            $categoryIds = [];
            $categoryNames = [];
            
            foreach ($userData["selected_categories"] as $category) {
                $categoryIds[] = $category['id'];
                $categoryNames[] = $category['name'];
                writeLog("Using selected category: " . $category['name'] . " with ID: " . $category['id']);
            }
            
            // إضافة الفئات للمنتج باستخدام IDs
            if (!empty($categoryIds)) {
                $product['categories'] = [];
                foreach ($categoryIds as $categoryId) {
                    $product['categories'][] = ['id' => $categoryId];
                }
                writeLog("Final category IDs for product: " . json_encode($categoryIds));
            }
            
            // إضافة الفئات أيضاً كـ meta_data ليظهر في الأسفل
            $product['meta_data'][] = [
                'key' => 'الفئات',
                'value' => implode(' > ', $categoryNames)
            ];
        } elseif (!empty($userData["categories"])) {
            // استخدام الفئات اليدوية إذا لم تكن هناك فئات مختارة
            $categoryIds = [];
            
            foreach ($userData["categories"] as $categoryName) {
                $categoryName = trim($categoryName);
                if (!empty($categoryName)) {
                    // البحث عن المجموعة أولاً
                    $searchUrl = "https://mesbah.ae/wp-json/wc/v3/products/categories?search=" . urlencode($categoryName);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $searchUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $searchResponse = curl_exec($ch);
                    $searchData = json_decode($searchResponse, true);
                    
                    if (!empty($searchData) && is_array($searchData)) {
                        // المجموعة موجودة، استخدم ID الموجود
                        $categoryIds[] = $searchData[0]['id'];
                        writeLog("Found existing category: " . $categoryName . " with ID: " . $searchData[0]['id']);
                    } else {
                        // إنشاء مجموعة جديدة
                        $createUrl = "https://mesbah.ae/wp-json/wc/v3/products/categories";
                        $categoryData = [
                            'name' => $categoryName
                        ];
                        
                        curl_setopt($ch, CURLOPT_URL, $createUrl);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($categoryData));
                        
                        $createResponse = curl_exec($ch);
                        $createData = json_decode($createResponse, true);
                        
                        if (isset($createData['id'])) {
                            $categoryIds[] = $createData['id'];
                            writeLog("Created new category: " . $categoryName . " with ID: " . $createData['id']);
                        } else {
                            writeLog("Failed to create category: " . $categoryName . " - " . $createResponse);
                        }
                    }
                    
                    curl_close($ch);
                }
            }
            
            // إضافة المجموعات للمنتج باستخدام IDs
            if (!empty($categoryIds)) {
                $product['categories'] = [];
                foreach ($categoryIds as $categoryId) {
                    $product['categories'][] = ['id' => $categoryId];
                }
                writeLog("Final category IDs for product: " . json_encode($categoryIds));
            }
            
            // إضافة المجموعات أيضاً كـ meta_data ليظهر في الأسفل
            $product['meta_data'][] = [
                'key' => 'المجموعات',
                'value' => implode(', ', $userData["categories"])
            ];
        }

        // إضافة المعلومات الإضافية في الوصف الطويل
        $extendedDescription = $userData["long_description"];
        
        // دمج المعلومات الإضافية العادية مع المعلومات الإضافية المختارة
        $allAdditionalInfo = $userData["additional_info"] ?? [];
        if (!empty($userData["current_additional_info"])) {
            $allAdditionalInfo = array_merge($allAdditionalInfo, $userData["current_additional_info"]);
        }
        
        if (!empty($allAdditionalInfo)) {
            $additionalInfoText = "\n\nℹ️ المعلومات الإضافية:\n";
            foreach ($allAdditionalInfo as $key => $value) {
                $additionalInfoText .= "• $key: $value\n";
            }
            $extendedDescription .= $additionalInfoText;
        }
        
        $product['description'] = $extendedDescription;

        // إضافة المعلومات الإضافية كـ meta_data لتظهر في الأسفل
        if (!empty($allAdditionalInfo)) {
            foreach ($allAdditionalInfo as $key => $value) {
                $product['meta_data'][] = [
                    'key' => '_' . $key, // إضافة underscore لجعلها تظهر في الأسفل
                    'value' => $value
                ];
            }
        }
        
        // إضافة SKU كـ meta_data ليظهر في الأسفل
        if (!empty($userData["sku"])) {
            $product['meta_data'][] = [
                'key' => 'رمز المنتج',
                'value' => $userData["sku"]
            ];
        }
        
        // إضافة المعلومات الإضافية كـ attributes عالمية لتظهر كفلاتر
        if (!empty($allAdditionalInfo)) {
            $product['attributes'] = [];
            
            writeLog("Processing " . count($allAdditionalInfo) . " additional info items as global attributes");
            
            foreach ($allAdditionalInfo as $key => $value) {
                // إنشاء أو الحصول على الـ attribute العالمي
                $attributeData = createOrGetGlobalAttribute($key, $value);
                
                if ($attributeData !== null && isset($attributeData['attribute_id'])) {
                    // استخدام الـ attribute العالمي (سيظهر كفلتر)
                    writeLog("✓ Attribute '$key' WILL appear as FILTER (ID: " . $attributeData['attribute_id'] . ", Slug: " . $attributeData['attribute_slug'] . ", Term: " . $value . ")");
                    
                    $product['attributes'][] = [
                        'id' => $attributeData['attribute_id'],
                        'name' => $key,
                        'position' => 0,
                        'visible' => true,
                        'variation' => false,
                        'options' => [$value]
                    ];
                } else {
                    // إذا فشل الإنشاء، استخدام custom attribute (لن يظهر كفلتر)
                    writeLog("✗ Attribute '$key' will NOT appear as filter (failed to create global attribute, Value: $value)");
                    $product['attributes'][] = [
                        'name' => $key,
                        'position' => 0,
                        'visible' => true,
                        'variation' => false,
                        'options' => [$value]
                    ];
                }
            }
            
            writeLog("✅ Total attributes added to product: " . count($product['attributes']));
        }
        


        writeLog("Final product categories: " . json_encode($product['categories']));
        writeLog("Product data summary:");
        writeLog("  - Name: " . ($product['name'] ?? 'N/A'));
        writeLog("  - Price: " . ($product['regular_price'] ?? 'N/A'));
        writeLog("  - Images count: " . count($product['images'] ?? []));
        writeLog("  - Categories count: " . count($product['categories'] ?? []));
        writeLog("  - Attributes count: " . count($product['attributes'] ?? []));
        writeLog("Full product data (first 1000 chars): " . substr(json_encode($product), 0, 1000));

        // 4. إرسال المنتج إلى WooCommerce
        $apiUrl = $woocommerceUrl . "/wp-json/wc/v3/products";
        writeLog("Sending POST request to: " . $apiUrl);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        writeLog("WooCommerce API Response - HTTP Code: " . $httpCode);
        writeLog("WooCommerce API Response - Response length: " . strlen($response));
        writeLog("WooCommerce API Response - First 500 chars: " . substr($response, 0, 500));

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            writeLog("Curl Error: " . $curlError);
            sendTelegramMessage($chatId, "❌ حدث خطأ في الاتصال: " . $curlError);
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        $result = json_decode($response, true);
        
        writeLog("Decoded API Response: " . json_encode($result));

        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            $productId = $result['id'];
            writeLog("Simple product created successfully with ID: " . $productId);
            
            $typeNames = [
                'simple' => 'بسيط',
                'variable' => 'متغير',
                'grouped' => 'مجموعة',
                'external' => 'خارجي'
            ];
            $productTypeName = $typeNames[$productType] ?? $productType;
            
            $successMessage = "✅ تم رفع المنتج بنجاح!\n\n";
            $successMessage .= "📦 رقم المنتج: " . $productId . "\n";
            $successMessage .= "📝 الاسم: " . $userData["product"]["name"] . "\n";
            $successMessage .= "📄 الوصف القصير: " . $shortDescription . "\n";
            $successMessage .= "🏷️ النوع: " . $productTypeName . "\n";
            $successMessage .= "📸 عدد الصور: " . count($allImages) . "\n";
            
            if (!empty($userData["sku"])) {
                $successMessage .= "🏷️ رمز SKU: " . $userData["sku"] . "\n";
            }
            
            if (!empty($userData["product_id"])) {
                $successMessage .= "🆔 رقم المنتج: " . $userData["product_id"] . "\n";
                
                // إنشاء رابط المنتج
                $productId = $userData["product_id"];
                
                // تنظيف رقم/رمز المنتج وإزالة الأحرف الخاصة
                $cleanProductId = preg_replace('/[^\p{L}\p{N}-]/u', '', $productId);
                $cleanProductId = strtolower($cleanProductId);
                
                // إنشاء slug باستخدام رقم/رمز المنتج فقط
                $slug = $cleanProductId;
                $productUrl = "https://mesbah.ae/ar/shop/" . $slug;
                
                $successMessage .= "🔗 رابط المنتج: " . $productUrl . "\n";
            }
            
            if (!empty($userData["barcode"])) {
                $successMessage .= "📊 الباركود: " . $userData["barcode"] . "\n";
            }
            
            if (!empty($userData["selected_categories"])) {
                $categoryNames = array_column($userData["selected_categories"], 'name');
                $successMessage .= "📂 الفئات: " . implode(' > ', $categoryNames) . "\n";
                $successMessage .= "✅ تم إضافة الفئات بنجاح!\n";
                $successMessage .= "💡 ملاحظة: الفئات تظهر كروابط قابلة للنقر في المتجر\n";
            } elseif (!empty($userData["categories"])) {
                $successMessage .= "📂 المجموعات: " . implode(', ', $userData["categories"]) . "\n";
                $successMessage .= "✅ تم إضافة المجموعات بنجاح!\n";
                $successMessage .= "💡 ملاحظة: المجموعات تظهر كروابط قابلة للنقر في المتجر\n";
            }
            
            if (!empty($allAdditionalInfo)) {
                $successMessage .= "\nℹ️ المعلومات الإضافية (ستظهر كفلاتر 🔍):\n";
                foreach ($allAdditionalInfo as $key => $value) {
                    $successMessage .= "• $key: $value\n";
                }
                $successMessage .= "\n✅ تم إنشاء الـ Attributes تلقائياً وستظهر كفلاتر قابلة للبحث!\n";
                $successMessage .= "💡 يمكن للعملاء الفلترة حسب: " . implode("، ", array_keys($allAdditionalInfo)) . "\n";
            }
            
            $successMessage .= "\n🌐 يمكنك الآن مشاهدة المنتج في متجرك!";
            
            sendTelegramMessage($chatId, $successMessage);
            return true;
        } else {
            $error = isset($result['message']) ? $result['message'] : 'خطأ غير معروف';
            $errorCode = isset($result['code']) ? $result['code'] : 'unknown';
            $errorDetails = isset($result['data']['params']) ? json_encode($result['data']['params']) : '';
            $errorData = isset($result['data']) ? json_encode($result['data']) : 'no data';
            
            writeLog("=== API ERROR DETECTED ===");
            writeLog("HTTP Code: " . $httpCode);
            writeLog("Error Code: " . $errorCode);
            writeLog("Error Message: " . $error);
            writeLog("Error Details: " . $errorDetails);
            writeLog("Error Data: " . $errorData);
            writeLog("Full API Response: " . $response);
            writeLog("========================");
            
            $errorMessage = "❌ فشل رفع المنتج";
            if ($error !== 'خطأ غير معروف') {
                $errorMessage .= ": " . $error;
            }
            if ($httpCode !== 200) {
                $errorMessage .= "\n📊 HTTP Code: " . $httpCode;
            }
            
            sendTelegramMessage($chatId, $errorMessage);
            return false;
        }
    } catch (Exception $e) {
        writeLog("Exception in uploadProduct: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ حدث خطأ: " . $e->getMessage());
        return false;
    }
}

function generateShortDescription($productName, $additionalInfo) {
    $description = $productName;
    
    if (!empty($additionalInfo)) {
        $description .= "\n\n";
        
        // إضافة النوع إذا كان موجوداً
        if (isset($additionalInfo['النوع'])) {
            $description .= "النوع: " . $additionalInfo['النوع'] . "\n";
        }
        
        // إضافة عدد الخرز إذا كان موجوداً
        if (isset($additionalInfo['عدد الخرز'])) {
            $description .= "عدد الخرز: " . $additionalInfo['عدد الخرز'] . "\n";
        }
        
        // إضافة القياس إذا كان موجوداً
        if (isset($additionalInfo['القياس'])) {
            $description .= "القياس: " . $additionalInfo['القياس'] . "\n";
        }
        
        // إضافة الوزن إذا كان موجوداً
        if (isset($additionalInfo['الوزن'])) {
            $description .= "الوزن: " . $additionalInfo['الوزن'] . "\n";
        }
        
        // إضافة نوع القصة إذا كان موجوداً
        if (isset($additionalInfo['نوع القصة'])) {
            $description .= "نوع القصة: " . $additionalInfo['نوع القصة'] . "\n";
        }
        
        // إضافة اسم الخراط إذا كان موجوداً
        if (isset($additionalInfo['اسم الخراط'])) {
            $description .= "اسم الخراط: " . $additionalInfo['اسم الخراط'] . "\n";
        }
        
        // إضافة نوع التطعيم إذا كان موجوداً
        if (isset($additionalInfo['نوع التطعيم'])) {
            $description .= "نوع التطعيم: " . $additionalInfo['نوع التطعيم'] . "\n";
        }
        
        // إزالة السطر الأخير الفارغ
        $description = rtrim($description, "\n");
        
    } else {
        // إذا لم تكن هناك معلومات إضافية، أضف وصف عام
        $description .= "\n\nمنتج عالي الجودة";
    }
    
    return $description;
}

function parseAdditionalInfo($text) {
    $info = [];
    
    // تقسيم النص إلى أسطر
    $lines = explode("\n", $text);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // البحث عن نمط "مفتاح: قيمة"
        if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            if (!empty($key) && !empty($value)) {
                $info[$key] = $value;
            }
        }
        // البحث عن نمط "مفتاح:قيمة" (بدون مسافة)
        elseif (preg_match('/^(.+?):(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            if (!empty($key) && !empty($value)) {
                $info[$key] = $value;
            }
        }
    }
    
    // إذا لم يتم العثور على أي معلومات، حاول تقسيم النص بطرق أخرى
    if (empty($info)) {
        // تقسيم بفواصل
        $parts = explode('،', $text);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // البحث عن نمط "مفتاح: قيمة" في كل جزء
            if (preg_match('/^(.+?):\s*(.+)$/', $part, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                if (!empty($key) && !empty($value)) {
                    $info[$key] = $value;
                }
            }
        }
    }
    
    return $info;
}

function handleTextInput($userId, $chatId, $text, &$usersData) {
    global $dataFile;
    
    switch ($usersData[$userId]["edit_mode"] ?? null) {
        case "editname":
            $usersData[$userId]["product"]["name"] = $text;
            $usersData[$userId]["edit_mode"] = null;
            saveData($usersData, $dataFile);
            sendTelegramMessage($chatId, "✅ تم تحديث الاسم بنجاح.");
            showMainKeyboard($chatId);
            break;
            

            

            
        case "editproductid":
            if (!empty(trim($text))) {
                $productId = trim($text);
                $usersData[$userId]["product_id"] = $productId;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث رقم/رمز المنتج بنجاح: $productId");
                showMainKeyboard($chatId);
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رقم/رمز منتج صالح.");
            }
            break;
            
        case "editsku":
            if (!empty(trim($text))) {
                $sku = trim($text);
                $usersData[$userId]["sku"] = $sku;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث رمز SKU بنجاح: $sku");
                showMainKeyboard($chatId);
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال رمز SKU (أرقام أو أحرف).");
            }
            break;
            
        case "editbarcode":
            if (!empty(trim($text))) {
                $barcode = trim($text);
                $usersData[$userId]["barcode"] = $barcode;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث الباركود بنجاح: $barcode");
                showMainKeyboard($chatId);
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال باركود صالح.");
            }
            break;
            

            
        case "editprice":
            if (is_numeric($text) && $text > 0) {
                $usersData[$userId]["product"]["price"] = $text;
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث السعر بنجاح.");
                showMainKeyboard($chatId);
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال سعر صالح.");
            }
            break;
            
        case "editstock":
            if (is_numeric($text) && $text >= 0) {
                $usersData[$userId]["stock_quantity"] = intval($text);
                $usersData[$userId]["edit_mode"] = null;
                saveData($usersData, $dataFile);
                sendTelegramMessage($chatId, "✅ تم تحديث المخزون بنجاح.");
                showMainKeyboard($chatId);
            } else {
                sendTelegramMessage($chatId, "⚠️ الرجاء إدخال كمية صحيحة.");
            }
            break;
            
        case "editcategories":
            $categories = array_map('trim', explode(',', $text));
            $usersData[$userId]["categories"] = $categories;
            $usersData[$userId]["edit_mode"] = null;
            saveData($usersData, $dataFile);
            sendTelegramMessage($chatId, "✅ تم تحديث المجموعات بنجاح: " . implode(', ', $categories));
            showMainKeyboard($chatId);
            break;
            
        case "editlongdesc":
            $usersData[$userId]["long_description"] = $text;
            $usersData[$userId]["edit_mode"] = null;
            saveData($usersData, $dataFile);
            sendTelegramMessage($chatId, "✅ تم تحديث الوصف الطويل بنجاح.");
            showMainKeyboard($chatId);
            break;
            
        case "editadditionalinfo":
            // التحقق من الخصائص المدعومة
            $supportedAttributes = ['النوع', 'عدد الخرز', 'الوزن', 'نوع القصة', 'اسم الخراط', 'نوع التطعيم'];
            $attributeFound = false;
            
            foreach ($supportedAttributes as $attributeName) {
                if (preg_match('/^' . preg_quote($attributeName) . ':\s*(.*)$/', $text, $matches)) {
                    $attributeFound = true;
                    
                    // جلب خصائص المنتجات من WordPress
                    $productAttributes = getWordPressProductAttributes();
                    
                    // البحث عن الخاصية المطلوبة
                    $targetAttribute = null;
                    foreach ($productAttributes as $attribute) {
                        if ($attribute['name'] === $attributeName) {
                            $targetAttribute = $attribute;
            break;
                        }
                    }
                    
                    if ($targetAttribute) {
                        // جلب قيم الخاصية
                        $attributeTerms = getAttributeTerms($targetAttribute['id']);
                        
                        if (!empty($attributeTerms)) {
                            // عرض قيم الخاصية كأزرار
                            $attributeButtons = [];
                            $currentRow = [];
                            
                            foreach ($attributeTerms as $term) {
                                $currentRow[] = $term['name'];
                                
                                if (count($currentRow) == 2) {
                                    $attributeButtons[] = $currentRow;
                                    $currentRow = [];
                                }
                            }
                            
                            // إضافة الصف الأخير إذا كان يحتوي على عناصر
                            if (!empty($currentRow)) {
                                $attributeButtons[] = $currentRow;
                            }
                            
                            // إضافة زر "تم الاختيار"
                            $attributeButtons[] = ['✅ تم الاختيار'];
                    
                    $replyMarkup = [
                                'keyboard' => $attributeButtons,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ];
                    
                            // حفظ البيانات المؤقتة
                            if (!isset($usersData[$userId]["current_additional_info"])) {
                                $usersData[$userId]["current_additional_info"] = [];
                            }
                            $usersData[$userId]["waiting_for_attribute_selection"] = true;
                            $usersData[$userId]["current_attribute_name"] = $attributeName;
                            $usersData[$userId]["available_attribute_terms"] = $attributeTerms;
                            $usersData[$userId]["edit_mode"] = "editadditionalinfo";
                    saveData($usersData, $dataFile);
                            
                            sendTelegramKeyboard($chatId, "📋 اختر $attributeName من القائمة أدناه:", $replyMarkup);
                            return;
                        }
                    }
                    
                    // إذا لم يتم العثور على القيم، أرسل رسالة خطأ
                    sendTelegramMessage($chatId, "⚠️ لم يتم العثور على قيم لـ $attributeName في الموقع.\n\n💡 يمكنك كتابة القيمة يدوياً أو تجربة خاصية أخرى.");
                    return;
                }
            }
            
            // إذا لم تكن أي من الخصائص المدعومة، استخدم الطريقة العادية
            $additionalInfo = parseAdditionalInfo($text);
            $usersData[$userId]["additional_info"] = $additionalInfo;
            $usersData[$userId]["edit_mode"] = null;
            saveData($usersData, $dataFile);
            
            $infoText = "";
            foreach ($additionalInfo as $key => $value) {
                $infoText .= "• $key: $value\n";
            }
            
            sendTelegramMessage($chatId, "✅ تم تحديث المعلومات الإضافية بنجاح:\n$infoText");
            showMainKeyboard($chatId);
            break;
            
        default:
            // إذا لم تكن في وضع التعديل، تجاهل النص
            break;
    }
}

function showMainKeyboard($chatId) {
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
    
    sendTelegramKeyboard($chatId, "📋 اختر من القائمة أدناه:", $replyMarkup);
}

function getTelegramFileUrl($fileId) {
    global $telegramToken;
    writeLog("Getting Telegram file URL for file_id: " . $fileId);
    
    $getFileUrl = "https://api.telegram.org/bot$telegramToken/getFile?file_id=" . urlencode($fileId);
    writeLog("Requesting URL: " . $getFileUrl);
    
    // استخدام cURL بدلاً من file_get_contents لمعالجة أفضل للأخطاء
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $getFileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curlError)) {
        writeLog("CURL Error getting file info: " . $curlError);
        writeLog("HTTP Code: " . $httpCode);
        return null;
    }
    
    if ($httpCode !== 200) {
        writeLog("Telegram API returned HTTP code: " . $httpCode);
        writeLog("Response: " . substr($response, 0, 500));
        return null;
    }
    
    $res = json_decode($response, true);
    
    if (!$res) {
        writeLog("Failed to decode JSON response: " . substr($response, 0, 500));
        return null;
    }
    
    if (!isset($res["ok"]) || !$res["ok"]) {
        writeLog("Telegram API returned error: " . json_encode($res));
        return null;
    }
    
    if (!isset($res["result"]["file_path"])) {
        writeLog("Telegram API response missing file_path. Full response: " . json_encode($res));
        return null;
    }
    
    $fileUrl = "https://api.telegram.org/file/bot$telegramToken/" . $res["result"]["file_path"];
    writeLog("Successfully got file URL: " . $fileUrl);
    return $fileUrl;
}

function sendTelegramMessage($chatId, $message) {
    global $telegramToken;
    
    try {
        $ch = curl_init("https://api.telegram.org/bot$telegramToken/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Telegram API Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (!$result['ok']) {
            writeLog("Telegram API Error: " . json_encode($result));
            return false;
        }
        
        writeLog("Message sent successfully to chat $chatId");
        return $response;
    } catch (Exception $e) {
        writeLog("Exception in sendTelegramMessage: " . $e->getMessage());
        return false;
    }
}

function sendTelegramKeyboard($chatId, $text, $keyboard) {
    global $telegramToken;
    
    try {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Telegram Keyboard API Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (!$result['ok']) {
            writeLog("Telegram Keyboard API Error: " . json_encode($result));
            return false;
        }
        
        writeLog("Keyboard sent successfully to chat $chatId");
        return $response;
    } catch (Exception $e) {
        writeLog("Exception in sendTelegramKeyboard: " . $e->getMessage());
        return false;
    }
}

function saveData($data, $file) {
    file_put_contents($file, json_encode($data));
}

function writeLog($message) {
    try {
        $logFile = __DIR__ . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}\n";
        
        // التأكد من أن المجلد قابل للكتابة
        if (!is_writable(dirname($logFile)) && !is_writable($logFile)) {
            error_log("Cannot write to log file: $logFile");
            return;
        }
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log("Error writing to log: " . $e->getMessage());
    }
}

// ===== دوال نظام كلمة المرور =====

// دالة للتحقق من أن المستخدم مصرح له
function isUserAuthorized($userId, $authorizedUsers) {
    return isset($authorizedUsers[$userId]);
}

// دالة لحفظ المستخدمين المصرح لهم
function saveAuthorizedUsers($authorizedUsers, $file) {
    file_put_contents($file, json_encode($authorizedUsers));
}

// ===== ملاحظات مهمة =====
/*
🔒 نظام كلمة المرور:
- كلمة المرور الافتراضية: mesbah2024
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



// إضافة دالة جديدة لجلب خصائص المنتجات من WordPress
function getWordPressProductAttributes() {
    global $woocommerceUrl, $consumerKey, $consumerSecret;
    
    try {
        // جلب خصائص المنتجات من WordPress
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/attributes";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Curl Error in getWordPressProductAttributes: " . curl_error($ch));
            curl_close($ch);
            return [];
        }
        
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $attributes = json_decode($response, true);
            if (is_array($attributes)) {
                writeLog("Successfully fetched " . count($attributes) . " product attributes from WordPress");
                return $attributes;
            }
        }
        
        writeLog("Failed to fetch product attributes. HTTP Code: $httpCode, Response: " . substr($response, 0, 200));
        return [];
        
    } catch (Exception $e) {
        writeLog("Exception in getWordPressProductAttributes: " . $e->getMessage());
        return [];
    }
}

// إضافة دالة جديدة لجلب قيم خاصية معينة
function getAttributeTerms($attributeId) {
    global $woocommerceUrl, $consumerKey, $consumerSecret;
    
    try {
        // جلب كل القيم بدون حد
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/attributes/$attributeId/terms?per_page=100&orderby=name&order=asc";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Curl Error in getAttributeTerms: " . curl_error($ch));
            curl_close($ch);
            return [];
        }
        
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $terms = json_decode($response, true);
            if (is_array($terms)) {
                writeLog("Successfully fetched " . count($terms) . " terms for attribute ID: $attributeId");
                
                // إذا كان هناك أكثر من 100 نتيجة، جلب المزيد
                if (count($terms) >= 100) {
                    $allTerms = $terms;
                    $page = 2;
                    
                    while (true) {
                        $nextUrl = $woocommerceUrl . "/wp-json/wc/v3/products/attributes/$attributeId/terms?per_page=100&page=$page&orderby=name&order=asc";
                        
                        // إنشاء curl handle جديد للصفحة التالية
                        $chNext = curl_init();
                        curl_setopt($chNext, CURLOPT_URL, $nextUrl);
                        curl_setopt($chNext, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chNext, CURLOPT_HTTPHEADER, [
                            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
                            'Content-Type: application/json'
                        ]);
                        curl_setopt($chNext, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($chNext, CURLOPT_TIMEOUT, 30);
                        
                        $nextResponse = curl_exec($chNext);
                        $nextHttpCode = curl_getinfo($chNext, CURLINFO_HTTP_CODE);
                        
                        if (curl_errno($chNext)) {
                            writeLog("Curl Error in getAttributeTerms pagination: " . curl_error($chNext));
                            curl_close($chNext);
                            break;
                        }
                        
                        curl_close($chNext);
                        
                        if ($nextHttpCode >= 200 && $nextHttpCode < 300) {
                            $nextTerms = json_decode($nextResponse, true);
                            if (is_array($nextTerms) && !empty($nextTerms)) {
                                $allTerms = array_merge($allTerms, $nextTerms);
                                $page++;
                                writeLog("Fetched additional " . count($nextTerms) . " terms for attribute ID: $attributeId (page $page)");
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                    
                    // إزالة التكرارات بناءً على اسم القيمة (name)
                    $uniqueTerms = [];
                    $seenNames = [];
                    foreach ($allTerms as $term) {
                        $termName = isset($term['name']) ? trim($term['name']) : '';
                        // استخدام اسم القيمة كمفتاح فريد
                        if (!empty($termName) && !isset($seenNames[$termName])) {
                            $uniqueTerms[] = $term;
                            $seenNames[$termName] = true;
                        }
                    }
                    
                    writeLog("Total terms fetched for attribute ID: $attributeId: " . count($allTerms) . " (unique: " . count($uniqueTerms) . ")");
                    return $uniqueTerms;
                }
                
                // إزالة التكرارات حتى للنتائج الأولى (في حالة وجود تكرارات في نفس الصفحة)
                $uniqueTerms = [];
                $seenNames = [];
                foreach ($terms as $term) {
                    $termName = isset($term['name']) ? trim($term['name']) : '';
                    if (!empty($termName) && !isset($seenNames[$termName])) {
                        $uniqueTerms[] = $term;
                        $seenNames[$termName] = true;
                    }
                }
                
                return $uniqueTerms;
            }
        }
        
        writeLog("Failed to fetch attribute terms. HTTP Code: $httpCode, Response: " . substr($response, 0, 200));
        return [];
        
    } catch (Exception $e) {
        writeLog("Exception in getAttributeTerms: " . $e->getMessage());
        return [];
    }
}

// إضافة دالة جديدة لجلب الفئات الرئيسية من WordPress
function getWordPressCategories() {
    global $woocommerceUrl, $consumerKey, $consumerSecret;
    
    try {
        // جلب الفئات الرئيسية فقط (التي ليس لها parent)
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/categories?parent=0&per_page=100&orderby=name&order=asc";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Curl Error in getWordPressCategories: " . curl_error($ch));
            curl_close($ch);
            return [];
        }
        
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $categories = json_decode($response, true);
            if (is_array($categories)) {
                writeLog("Successfully fetched " . count($categories) . " main categories from WordPress");
                if (!empty($categories)) {
                    writeLog("Available main categories: " . json_encode(array_column($categories, 'name')));
                    // تسجيل تفاصيل كل فئة رئيسية
                    foreach ($categories as $category) {
                        writeLog("Main Category: " . $category['name'] . " (ID: " . $category['id'] . ", Parent: " . ($category['parent'] ?? 'none') . ")");
                    }
                }
                return $categories;
            }
        }
        
        writeLog("Failed to fetch main categories. HTTP Code: $httpCode, Response: " . substr($response, 0, 200));
        return [];
        
    } catch (Exception $e) {
        writeLog("Exception in getWordPressCategories: " . $e->getMessage());
        return [];
    }
}

// إضافة دالة جديدة لجلب الفئات الفرعية
function getWordPressSubCategories($parentId) {
    global $woocommerceUrl, $consumerKey, $consumerSecret;
    
    try {
        // جلب الفئات الفرعية من WordPress
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/categories?parent=$parentId&per_page=100&orderby=name&order=asc";
        writeLog("Fetching sub-categories from URL: " . $url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Curl Error in getWordPressSubCategories: " . curl_error($ch));
            curl_close($ch);
            return [];
        }
        
        curl_close($ch);
        
        writeLog("Sub-categories API Response (HTTP $httpCode): " . substr($response, 0, 500));
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $subCategories = json_decode($response, true);
            if (is_array($subCategories)) {
                writeLog("Successfully fetched " . count($subCategories) . " sub-categories for parent ID: $parentId");
                if (!empty($subCategories)) {
                    writeLog("Sub-categories found: " . json_encode(array_column($subCategories, 'name')));
                }
                return $subCategories;
            }
        }
        
        writeLog("Failed to fetch sub-categories. HTTP Code: $httpCode, Response: " . substr($response, 0, 200));
        return [];
        
    } catch (Exception $e) {
        writeLog("Exception in getWordPressSubCategories: " . $e->getMessage());
        return [];
    }
}

// إضافة دالة جديدة لمعالجة اختيار خاصية المنتج
function handleProductAttributeSelection($userId, $chatId, $attributeName, &$usersData) {
    global $dataFile;
    
    // حفظ الخاصية المختارة
    if (!isset($usersData[$userId]["selected_attributes"])) {
        $usersData[$userId]["selected_attributes"] = [];
    }
    
    $usersData[$userId]["selected_attributes"][] = $attributeName;
    saveData($usersData, $dataFile);
    
    sendTelegramMessage($chatId, "✅ تم اختيار الخاصية: $attributeName\n\n📋 الخصائص المختارة (" . count($usersData[$userId]["selected_attributes"]) . "):\n" . implode("\n", $usersData[$userId]["selected_attributes"]));
    
    // عرض زر "إنهاء اختيار الخصائص"
    $finishKeyboard = [
        ['✅ إنهاء اختيار الخصائص'],
        ['➕ إضافة خاصية جديدة']
    ];
    
    $replyMarkup = [
        'keyboard' => $finishKeyboard,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    sendTelegramKeyboard($chatId, "💡 يمكنك اختيار المزيد من الخصائص أو الضغط على '✅ إنهاء اختيار الخصائص' للمتابعة", $replyMarkup);
}

// دالة لإنشاء أو الحصول على attribute عالمي في WooCommerce
function createOrGetGlobalAttribute($attributeName, $termValue) {
    global $woocommerceUrl, $consumerKey, $consumerSecret;
    
    try {
        // تنظيف اسم الـ attribute
        $cleanAttributeName = trim($attributeName);
        $cleanTermValue = trim($termValue);
        
        // إنشاء slug للـ attribute (إزالة المسافات واستخدام الأحرف العربية)
        $attributeSlug = 'pa_' . sanitizeSlug($cleanAttributeName);
        
        writeLog("Creating/Getting global attribute: $cleanAttributeName (slug: $attributeSlug) with term: $cleanTermValue");
        
        // 1. البحث عن الـ attribute أو إنشاؤه
        $attributeId = null;
        
        // جلب جميع الـ attributes
        $url = $woocommerceUrl . "/wp-json/wc/v3/products/attributes";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            writeLog("Curl Error in createOrGetGlobalAttribute (GET): " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $attributes = json_decode($response, true);
            
            // البحث عن الـ attribute بالاسم
            foreach ($attributes as $attr) {
                if ($attr['name'] === $cleanAttributeName) {
                    $attributeId = $attr['id'];
                    writeLog("Found existing attribute: $cleanAttributeName with ID: $attributeId");
                    break;
                }
            }
        }
        
        // 2. إذا لم يوجد، إنشاء الـ attribute
        if ($attributeId === null) {
            $createAttributeData = [
                'name' => $cleanAttributeName,
                'slug' => $attributeSlug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => true
            ];
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createAttributeData));
            
            $createResponse = curl_exec($ch);
            $createHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($createHttpCode >= 200 && $createHttpCode < 300) {
                $createdAttribute = json_decode($createResponse, true);
                if (isset($createdAttribute['id'])) {
                    $attributeId = $createdAttribute['id'];
                    writeLog("✓ Created new global attribute: $cleanAttributeName with ID: $attributeId");
                } else {
                    writeLog("✗ Failed to create attribute: " . $createResponse);
                    curl_close($ch);
                    return null;
                }
            } else {
                writeLog("✗ Failed to create attribute (HTTP $createHttpCode): " . $createResponse);
                curl_close($ch);
                return null;
            }
        }
        
        // 3. إضافة الـ term للـ attribute إذا لم يكن موجوداً
        $termUrl = $woocommerceUrl . "/wp-json/wc/v3/products/attributes/$attributeId/terms";
        
        // جلب جميع الـ terms الموجودة
        curl_setopt($ch, CURLOPT_URL, $termUrl . "?per_page=100");
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        
        $termsResponse = curl_exec($ch);
        $termsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $termExists = false;
        $termId = null;
        
        if ($termsHttpCode >= 200 && $termsHttpCode < 300) {
            $terms = json_decode($termsResponse, true);
            
            // البحث عن الـ term بالاسم
            foreach ($terms as $term) {
                if ($term['name'] === $cleanTermValue) {
                    $termExists = true;
                    $termId = $term['id'];
                    writeLog("Found existing term: $cleanTermValue with ID: $termId for attribute: $cleanAttributeName");
                    break;
                }
            }
        }
        
        // 4. إنشاء الـ term إذا لم يكن موجوداً
        if (!$termExists) {
            $createTermData = [
                'name' => $cleanTermValue,
                'slug' => sanitizeSlug($cleanTermValue)
            ];
            
            curl_setopt($ch, CURLOPT_URL, $termUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createTermData));
            
            $createTermResponse = curl_exec($ch);
            $createTermHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($createTermHttpCode >= 200 && $createTermHttpCode < 300) {
                $createdTerm = json_decode($createTermResponse, true);
                if (isset($createdTerm['id'])) {
                    $termId = $createdTerm['id'];
                    writeLog("✓ Created new term: $cleanTermValue with ID: $termId for attribute: $cleanAttributeName");
                } else {
                    writeLog("✗ Failed to create term: " . $createTermResponse);
                }
            } else {
                writeLog("✗ Failed to create term (HTTP $createTermHttpCode): " . $createTermResponse);
            }
        }
        
        curl_close($ch);
        
        // إرجاع البيانات
        return [
            'attribute_id' => $attributeId,
            'attribute_name' => $cleanAttributeName,
            'attribute_slug' => $attributeSlug,
            'term_name' => $cleanTermValue,
            'term_id' => $termId
        ];
        
    } catch (Exception $e) {
        writeLog("Exception in createOrGetGlobalAttribute: " . $e->getMessage());
        return null;
    }
}

// دالة لتنظيف النصوص وإنشاء slug
function sanitizeSlug($text) {
    // إزالة المسافات واستبدالها بـ -
    $slug = str_replace(' ', '-', $text);
    // إزالة الأحرف الخاصة ما عدا الأحرف العربية والإنجليزية والأرقام والشرطة
    $slug = preg_replace('/[^\p{Arabic}\p{L}\p{N}-]/u', '', $slug);
    // تحويل إلى أحرف صغيرة
    $slug = mb_strtolower($slug, 'UTF-8');
    // إزالة الشرطات المتعددة
    $slug = preg_replace('/-+/', '-', $slug);
    // إزالة الشرطات من البداية والنهاية
    $slug = trim($slug, '-');
    
    return $slug;
}
?>





