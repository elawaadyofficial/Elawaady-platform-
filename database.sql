-- ############################################################################
-- #  DANGER — THIS FILE IS A BOOTSTRAP, NOT A MIGRATION.                      #
-- #                                                                          #
-- #  It begins with DROP TABLE. Running it against a database that holds      #
-- #  real rows destroys them. It exists only to create an empty catalogue on  #
-- #  a brand-new database.                                                    #
-- #                                                                          #
-- #  Do not pipe this file into mysql by hand. Run:                           #
-- #                                                                          #
-- #      php bootstrap.php                                                    #
-- #                                                                          #
-- #  which refuses to proceed if any table in the target database contains    #
-- #  data, and then hands off to `php migrate.php` for everything since.      #
-- #                                                                          #
-- #  To add or change schema, write a file in migrations/. Never edit this    #
-- #  one, and never run it on staging or production.                         #
-- ############################################################################

CREATE DATABASE IF NOT EXISTS elawaady_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elawaady_store;

DROP TABLE IF EXISTS store_services;
DROP TABLE IF EXISTS store_subcategories;
DROP TABLE IF EXISTS store_categories;

CREATE TABLE store_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '✨',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE store_subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '•',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE store_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    subcategory_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    service_link VARCHAR(255),
    status VARCHAR(50) DEFAULT 'متاحة',
    image VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (subcategory_id) REFERENCES store_subcategories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (1, 'خدمات فيسبوك', 'خدمات متابعين ولايكات ومشاهدات وإدارة وإعلانات وتوثيق فيسبوك.', '📘', 1);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (2, 'خدمات إنستجرام', 'خدمات نمو وإدارة وتوثيق وإعلانات وحماية حسابات إنستجرام.', '📸', 2);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (3, 'خدمات تيك توك', 'خدمات متابعين ومشاهدات وتوثيق وإدارة وحماية حسابات تيك توك.', '🎵', 3);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (4, 'خدمات سناب شات', 'خدمات سناب شات: حسابات، توثيق، إعلانات، تفاعل، واستشارات.', '👻', 4);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (5, 'خدمات يوتيوب', 'خدمات مشتركين ومشاهدات وساعات مشاهدة وإدارة وتوثيق قنوات يوتيوب.', '▶️', 5);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (6, 'خدمات تويتر X', 'خدمات حسابات وتوثيق وإعلانات ونمو وحماية حسابات تويتر X.', '𝕏', 6);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (7, 'خدمات تيليجرام', 'خدمات قنوات وجروبات وبوتات وتفاعل وحماية تيليجرام.', '✈️', 7);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (8, 'خدمات واتساب', 'واتساب بزنس، ربط بالموقع، بوتات، حملات، رسائل جماعية، وتوثيق.', '💬', 8);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (9, 'خدمات ديسكورد', 'إعداد وإدارة سيرفرات ديسكورد وبوتات وحماية وتصميم قنوات.', '🎮', 9);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (10, 'خدمات لينكدإن', 'تحسين الحسابات، إدارة المحتوى، إعلانات وبناء الهوية المهنية.', '💼', 10);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (11, 'التوثيق الرسمي', 'توثيق المنصات وتجهيز الملفات ومراجعة الأهلية والاستشارات.', '✅', 11);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (12, 'إدارة الحسابات', 'إدارة صفحات وحسابات وقنوات وجدولة محتوى وردود وتحليلات.', '🧭', 12);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (13, 'الميديا باينج والإعلانات', 'حملات إعلانية على Meta وGoogle وTikTok وYouTube وغيرها.', '📣', 13);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (14, 'التصميمات', 'تصميم سوشيال ميديا، هويات، لوجو، بنرات، أغلفة، ومتاجر.', '🎨', 14);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (15, 'المونتاج وصناعة المحتوى', 'مونتاج ريلز وفيديوهات وإعلانات وسكريبتات وتعليق صوتي.', '🎬', 15);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (16, 'البرمجة والبوتات', 'بوتات، أنظمة طلبات، API، لوحات تحكم وأنظمة تلقائية.', '🤖', 16);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (17, 'المواقع والمتاجر', 'تصميم وبرمجة مواقع ومتاجر وصفحات هبوط وربط دومين واستضافة.', '🌐', 17);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (18, 'الدومينات والاستضافة', 'شراء وتجديد ونقل دومينات، استضافة، VPS، SSL، DNS وبريد احترافي.', '🛡️', 18);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (19, 'خدمات جوجل', 'Google Ads وWorkspace وAnalytics وSearch Console وخدمات جوجل بزنس.', '🔎', 19);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (20, 'خدمات ميتا', 'Facebook Ads وInstagram Ads وMeta Business Manager وMeta Verified.', '♾️', 20);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (21, 'الحسابات الجاهزة', 'حسابات جاهزة لمنصات متعددة، حسابات موثقة، ربح، وإعلانات.', '🛒', 21);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (22, 'الصفحات والقنوات الجاهزة', 'صفحات وقنوات وسيرفرات جاهزة للاستخدام أو البراندات.', '📦', 22);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (23, 'نقل الملكية والاستلام', 'نقل ملكية حسابات وصفحات وقنوات وتغيير بيانات وتأمين بعد النقل.', '🔐', 23);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (24, 'الحماية والاسترجاع', 'حماية واسترجاع حسابات وصفحات وقنوات ومراجعة أمان وحل مشاكل.', '🧰', 24);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (25, 'خدمات الربح والمنصات', 'تفعيل الربح، مراجعة الحسابات، تجهيز وسحب أرباح واستشارات.', '💰', 25);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (26, 'الألعاب والشحن', 'شحن ألعاب، بطاقات ألعاب، شدات وجواهر وعملات وحسابات ألعاب.', '🎮', 26);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (27, 'الكروت والبطاقات الرقمية', 'بطاقات جوجل بلاي وآيتونز وستيم وبلايستيشن وإكس بوكس وشحن رقمي.', '💳', 27);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (28, 'الخدمات التعليمية والكورسات', 'كورسات ميديا باينج، تصميم، مونتاج، تجارة إلكترونية، وجلسات تدريب.', '🎓', 28);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (29, 'الخدمات التجارية للشركات', 'إدارة حسابات شركات، هوية رقمية، حملات، بروفايل وبناء أنظمة طلبات.', '🏢', 29);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (30, 'العروض والباقات', 'باقات شهرية وإعلانية وتصميم وإدارة وباقات شركات ومخصصة.', '🎁', 30);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (31, 'الخدمات الخاصة', 'VIP، تنفيذ خاص، خدمات مستعجلة، حلول مخصصة وإدارة مشاريع رقمية.', '⭐', 31);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (32, 'الوساطة الرقمية', 'وساطة بيع وشراء حسابات وصفحات وقنوات وخدمات وتصميم وبرمجة.', '🤝', 32);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (33, 'حسابات الذكاء الاصطناعي AI', 'حسابات ChatGPT وClaude وGemini وMidjourney وCanva Pro وغيرها.', '🧠', 33);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (34, 'Microsoft 365 وخدمات الأعمال', 'Microsoft 365 Personal/Family/Business وOutlook وTeams وExchange.', 'Ⓜ️', 34);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (35, 'الاشتراكات الرقمية', 'Netflix وSpotify وYouTube Premium وShahid وVPN واشتراكات تعليمية.', '🎟️', 35);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (36, 'حسابات وبرامج الأعمال', 'Adobe وCanva وZoom وNotion وTrello وSlack وCRM وأدوات إدارة العمل.', '🧩', 36);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (37, 'قسم الموردين والشركاء', 'تسجيل مورد، عرض خدمة أو منتج، إدارة نسبة المورد والأرباح.', '🏷️', 37);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (38, 'قسم التسويق بالعمولة', 'منتجات وخدمات بنظام العمولة، عروض، كوبونات ومنتجات مطلوبة.', '📈', 38);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (39, 'أفلييت أمازون', 'إلكترونيات، موبايلات، اكسسوارات، أجهزة، عروض وكوبونات أمازون.', '🛍️', 39);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (40, 'أفلييت نون', 'إلكترونيات، موبايلات، عروض نون، جمال وعناية وكوبونات نون.', '🛒', 40);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (41, 'منتجات بنظام العمولة', 'منتجات وخدمات خارجية مقابل نسبة أو عروض حصرية وموردين.', '🔁', 41);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (42, 'Marketplace', 'خدمات ومنتجات مقدمة من الغير، فريلانسرز، موردين وشركاء متجر.', '🏬', 42);
INSERT INTO store_categories (id, name, description, icon, sort_order) VALUES (43, 'اعرض خدمتك عندنا', 'إضافة خدمة أو منتج أو مورد أو شريك وعرض مقابل عمولة أو نسبة.', '🚀', 43);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (1, 1, 'متابعين فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (2, 1, 'لايكات فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (3, 1, 'إعلانات فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (4, 1, 'توثيق صفحات فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (5, 1, 'إدارة صفحات فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (6, 1, 'استرجاع حسابات فيسبوك', 'قسم فرعي ضمن خدمات فيسبوك', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (7, 2, 'متابعين إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (8, 2, 'لايكات إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (9, 2, 'إعلانات إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (10, 2, 'توثيق إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (11, 2, 'إدارة حسابات إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (12, 2, 'حماية حسابات إنستجرام', 'قسم فرعي ضمن خدمات إنستجرام', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (13, 3, 'متابعين تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (14, 3, 'مشاهدات تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (15, 3, 'إعلانات تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (16, 3, 'توثيق تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (17, 3, 'إدارة حسابات تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (18, 3, 'حماية حسابات تيك توك', 'قسم فرعي ضمن خدمات تيك توك', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (19, 4, 'متابعين سناب شات', 'قسم فرعي ضمن خدمات سناب شات', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (20, 4, 'مشاهدات سناب شات', 'قسم فرعي ضمن خدمات سناب شات', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (21, 4, 'توثيق سناب شات', 'قسم فرعي ضمن خدمات سناب شات', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (22, 4, 'إدارة حسابات سناب شات', 'قسم فرعي ضمن خدمات سناب شات', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (23, 4, 'إعلانات سناب شات', 'قسم فرعي ضمن خدمات سناب شات', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (24, 4, 'حسابات سناب جاهزة', 'قسم فرعي ضمن خدمات سناب شات', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (25, 5, 'مشتركين يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (26, 5, 'مشاهدات يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (27, 5, 'ساعات مشاهدة يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (28, 5, 'إدارة قنوات يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (29, 5, 'توثيق قنوات يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (30, 5, 'الربح من يوتيوب', 'قسم فرعي ضمن خدمات يوتيوب', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (31, 11, 'توثيق فيسبوك', 'قسم فرعي ضمن التوثيق الرسمي', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (32, 11, 'توثيق إنستجرام', 'قسم فرعي ضمن التوثيق الرسمي', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (33, 11, 'توثيق تيك توك', 'قسم فرعي ضمن التوثيق الرسمي', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (34, 11, 'توثيق سناب شات', 'قسم فرعي ضمن التوثيق الرسمي', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (35, 11, 'توثيق جوجل بزنس', 'قسم فرعي ضمن التوثيق الرسمي', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (36, 11, 'توثيق واتساب بزنس', 'قسم فرعي ضمن التوثيق الرسمي', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (37, 13, 'إعلانات ميتا', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (38, 13, 'إعلانات جوجل', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (39, 13, 'إعلانات تيك توك', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (40, 13, 'إعلانات سناب شات', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (41, 13, 'إعداد البيكسل', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (42, 13, 'تحليل الحملات', 'قسم فرعي ضمن الميديا باينج والإعلانات', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (43, 16, 'بوتات تيليجرام', 'قسم فرعي ضمن البرمجة والبوتات', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (44, 16, 'بوتات واتساب', 'قسم فرعي ضمن البرمجة والبوتات', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (45, 16, 'أنظمة الطلبات', 'قسم فرعي ضمن البرمجة والبوتات', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (46, 16, 'لوحات تحكم', 'قسم فرعي ضمن البرمجة والبوتات', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (47, 16, 'API', 'قسم فرعي ضمن البرمجة والبوتات', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (48, 16, 'إشعارات تلقائية', 'قسم فرعي ضمن البرمجة والبوتات', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (49, 17, 'تصميم مواقع', 'قسم فرعي ضمن المواقع والمتاجر', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (50, 17, 'برمجة مواقع', 'قسم فرعي ضمن المواقع والمتاجر', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (51, 17, 'متاجر إلكترونية', 'قسم فرعي ضمن المواقع والمتاجر', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (52, 17, 'صفحات هبوط', 'قسم فرعي ضمن المواقع والمتاجر', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (53, 17, 'حماية المواقع', 'قسم فرعي ضمن المواقع والمتاجر', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (54, 17, 'ربط دومين واستضافة', 'قسم فرعي ضمن المواقع والمتاجر', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (55, 21, 'حسابات فيسبوك', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (56, 21, 'حسابات إنستجرام', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (57, 21, 'حسابات تيك توك', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (58, 21, 'حسابات سناب شات', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (59, 21, 'حسابات يوتيوب', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (60, 21, 'حسابات ربح', 'قسم فرعي ضمن الحسابات الجاهزة', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (61, 33, 'ChatGPT', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (62, 33, 'Claude', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (63, 33, 'Gemini', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (64, 33, 'Midjourney', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (65, 33, 'Canva Pro', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (66, 33, 'Jasper AI', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (67, 33, 'Runway', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 7);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (68, 33, 'Copy.ai', 'قسم فرعي ضمن حسابات الذكاء الاصطناعي AI', '•', 8);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (69, 34, 'Microsoft 365 Personal', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (70, 34, 'Microsoft 365 Family', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (71, 34, 'Business Basic', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (72, 34, 'Business Standard', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (73, 34, 'Business Premium', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (74, 34, 'Teams', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (75, 34, 'Exchange', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 7);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (76, 34, 'OneDrive', 'قسم فرعي ضمن Microsoft 365 وخدمات الأعمال', '•', 8);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (77, 35, 'Netflix', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (78, 35, 'Spotify', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (79, 35, 'YouTube Premium', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (80, 35, 'Shahid', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (81, 35, 'Amazon Prime Video', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (82, 35, 'VPN', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 6);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (83, 35, 'OSN', 'قسم فرعي ضمن الاشتراكات الرقمية', '•', 7);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (84, 42, 'خدمات مقدمة من الغير', 'قسم فرعي ضمن Marketplace', '•', 1);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (85, 42, 'منتجات مقدمة من الغير', 'قسم فرعي ضمن Marketplace', '•', 2);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (86, 42, 'فريلانسرز', 'قسم فرعي ضمن Marketplace', '•', 3);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (87, 42, 'موردين', 'قسم فرعي ضمن Marketplace', '•', 4);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (88, 42, 'شركاء المتجر', 'قسم فرعي ضمن Marketplace', '•', 5);
INSERT INTO store_subcategories (id, category_id, name, description, icon, sort_order) VALUES (89, 42, 'تقييمات العملاء', 'قسم فرعي ضمن Marketplace', '•', 6);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (1, 1, 'متابعين فيسبوك', 'خدمة زيادة متابعين فيسبوك حسب المتاح مع توضيح النوع والمدة قبل التنفيذ.', 0, 'contact.php', 'متاحة', 1);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (1, 4, 'توثيق صفحات فيسبوك', 'مراجعة وتجهيز طلبات توثيق صفحات فيسبوك حسب المتطلبات.', 0, 'contact.php', 'متاحة', 2);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (2, 8, 'لايكات إنستجرام', 'خدمة لايكات إنستجرام للحملات والمحتوى مع تنفيذ منظم.', 0, 'contact.php', 'متاحة', 3);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (3, 15, 'مشاهدات تيك توك', 'مشاهدات تيك توك مناسبة لرفع الانتشار حسب الباقة.', 0, 'contact.php', 'متاحة', 4);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (4, 21, 'توثيق سناب شات', 'تجهيز ومتابعة توثيق حسابات سناب شات وفق المتطلبات المناسبة.', 0, 'contact.php', 'متاحة', 5);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (4, 24, 'حسابات سناب شات جاهزة', 'حسابات سناب جاهزة حسب المتوفر مع توضيح حالة الحساب وطريقة النقل.', 0, 'contact.php', 'متاحة', 6);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (5, 27, 'ساعات مشاهدة يوتيوب', 'خدمة ساعات مشاهدة يوتيوب حسب شروط القناة وحالة المحتوى.', 0, 'contact.php', 'متاحة', 7);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (11, 32, 'توثيق إنستجرام', 'خدمة تجهيز ومتابعة توثيق حسابات إنستجرام.', 0, 'contact.php', 'متاحة', 8);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (13, 37, 'إعلانات ممولة Meta', 'إدارة حملات فيسبوك وإنستجرام مع متابعة النتائج والتحسين.', 0, 'contact.php', 'متاحة', 9);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (14, NULL, 'تصميم هوية بصرية', 'تصميم لوجو وهوية بصرية وبنرات احترافية.', 0, 'contact.php', 'متاحة', 10);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (16, 43, 'برمجة بوت تيليجرام', 'برمجة بوت تيليجرام للطلبات أو الخدمات أو المتاجر.', 0, 'contact.php', 'متاحة', 11);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (17, 51, 'برمجة متجر إلكتروني', 'تصميم وبرمجة متجر إلكتروني قابل للتطوير والربط بالدفع لاحقًا.', 0, 'contact.php', 'متاحة', 12);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (21, 58, 'حساب سناب شات جاهز', 'حساب سناب شات جاهز حسب المواصفات المتاحة.', 0, 'contact.php', 'متاحة', 13);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (23, NULL, 'نقل ملكية حساب', 'تنظيم عملية نقل ملكية حساب مع تأمين بيانات بعد النقل.', 0, 'contact.php', 'متاحة', 14);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (24, NULL, 'استرجاع حساب مخترق', 'مراجعة حالة الحساب المخترق وتحديد إمكانية الاسترجاع.', 0, 'contact.php', 'متاحة', 15);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (33, 61, 'ChatGPT Plus', 'حساب أو اشتراك ChatGPT Plus حسب المتاح.', 0, 'contact.php', 'متاحة', 16);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (33, 64, 'Midjourney', 'حساب أو اشتراك Midjourney لتصميم الصور بالذكاء الاصطناعي.', 0, 'contact.php', 'متاحة', 17);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (34, 72, 'Microsoft 365 Business Standard', 'اشتراك Microsoft 365 Business Standard للشركات.', 0, 'contact.php', 'متاحة', 18);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (35, 77, 'Netflix', 'اشتراك Netflix حسب المتوفر والباقات المتاحة.', 0, 'contact.php', 'متاحة', 19);
INSERT INTO store_services (category_id, subcategory_id, name, description, price, service_link, status, sort_order) VALUES (42, 84, 'خدمة مقدمة من مورد', 'خدمة Marketplace من مورد أو شريك خارجي تحت مراجعة المتجر.', 0, 'contact.php', 'متاحة', 20);