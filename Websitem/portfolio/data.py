"""
Static content for the CV site: no database, this is a personal portfolio
that doesn't change at runtime, so a plain module beats models + migrations.

This module holds the Turkish half; data_en.py holds the English one. Django's
i18n machinery does the routing and picks the active language, but the content
itself stays as plain Python — a .po file for paragraphs this long would be a
worse thing to maintain than two readable dictionaries.
"""

from . import data_en

PROFILE = {
    "name": "Uğur Polat",
    "role": "Bilgisayar Mühendisi & Full-Stack Geliştirici",
    "tagline": "Java'dan Django'ya, Unity'den framework'süz PHP'ye — her projede sıfırdan inşa etmeyi seçtim.",
    "age": 23,
    "location": "İstanbul, Bahçelievler",
    "email": "ugurplt8@gmail.com",
    "status": "Ecole 42 İstanbul'da eğitimine devam ediyor",
    "bio": [
        "Bolu Abant İzzet Baysal Üniversitesi Bilgisayar Mühendisliği mezunuyum. Şu an İstanbul'da, notların ve öğretmenlerin yerini akran değerlendirmesinin aldığı, tamamen proje tabanlı bir müfredat yürüten Ecole 42'de eğitimime devam ediyorum.",
        "Masaüstünde yazdığım bir Java kule savunma oyunundan Unity'deki iki farklı oyuna, framework kullanmadan sıfırdan yazdığım bir PHP yönetim platformundan şu an incelemekte olduğunuz bu Django sitesine kadar — önce temelleri anlayıp öyle inşa etmeyi tercih ediyorum. Aşağıda gördüğünüz dört proje de bu yaklaşımın ürünü.",
        "Yeni bir şey öğrenmekten her zaman keyif alırım; Ecole 42'nin akran değerlendirmeli yapısı da tam olarak bu yüzden bana uyuyor — hem öğrenmeyi hem de takım içinde çalışmayı zorunlu kılıyor. Yazılım geliştirme tarafında olduğu kadar farklı sektörlerde de çalışma tecrübem var. İstanbul Bahçelievler'de yaşıyorum.",
    ],
    "education": [
        {
            "school": "Ecole 42 İstanbul",
            "degree": "Yazılım Mühendisliği — Proje Tabanlı Eğitim",
            "period": "Devam ediyor",
            "note": "Notsuz, öğretmensiz, tamamen akran değerlendirmeli (peer-to-peer) ve proje odaklı müfredat.",
        },
        {
            "school": "Bolu Abant İzzet Baysal Üniversitesi",
            "degree": "Bilgisayar Mühendisliği",
            "period": "Mezun",
            "note": "Bitirme dönemimde sıfırdan yazdığım bir Java kule savunma oyunu dahil olmak üzere çeşitli yazılım projeleri.",
        },
    ],
    "skills": [
        {
            "group": "Programlama Dilleri",
            "featured": True,
            "items": [
                {"name": "C", "level": "İleri", "bars": 5},
                {"name": "Java", "level": "İleri", "bars": 5},
                {"name": "PHP", "level": "İleri", "bars": 5},
                {"name": "Python", "level": "Orta", "bars": 3},
            ],
        },
        {
            "group": "Web Geliştirme",
            "items": [
                {"name": "HTML", "level": "İleri"},
                {"name": "CSS", "level": "İleri"},
                {"name": "JavaScript", "level": "İleri"},
                {"name": "Django", "level": "Bu site ile"},
            ],
        },
        {
            "group": "Oyun Geliştirme",
            "items": [
                {"name": "Unity", "level": "İleri"},
                {"name": "Unreal Engine", "level": "Orta"},
            ],
        },
        {
            "group": "Araçlar",
            "items": [
                {"name": "Git", "level": ""},
                {"name": "MySQL", "level": ""},
                {"name": "Composer", "level": ""},
                {"name": "Eclipse / Visual Studio", "level": ""},
            ],
        },
    ],
    "highlights": [
        "4 uçtan uca proje: 2 masaüstü/Unity oyunu, 1 framework'süz PHP platformu, 1 Django sitesi",
        "Oyun motoru kullanmadan Java + Swing ile yazılmış bir kule savunma oyunu",
        "Laravel/Symfony'siz, ham PDO ile yazılmış 20 tablolu bir belgelendirme platformu",
        "Unity'de NavMesh tabanlı AI ve 90+ özel Editor aracıyla inşa edilmiş bir oyun",
    ],
    "soft_skills": [
        "Öğrenmekten keyif alırım", "Takım çalışmasına yatkınım",
        "Meraklı ve araştırmacıyım", "Sıfırdan sisteme dökmeyi severim",
    ],
}

PROJECTS = [
    {
        "slug": "oyunum",
        "name": "Kule Savunma Oyunu (Java)",
        "kind": "Masaüstü Oyun",
        "tagline": "Harici bir oyun motoru kullanmadan, sıfırdan Java ile yazılmış bir kule savunma oyunu.",
        "accent": "#f2b84c",
        "year": "Üniversite Projesi",
        "cover": "portfolio/img/oyunum/combat.jpg",
        "summary": (
            "Bolu Abant İzzet Baysal Üniversitesi'ndeki bilgisayar mühendisliği eğitimim sırasında, "
            "Unity ya da Godot gibi hiçbir hazır motora dokunmadan, sadece Java ve Swing ile uçtan uca "
            "yazdığım bir kule savunma oyunu. Haritayı çizen sprite motorundan düşman dalgalarını yöneten "
            "yapay zekaya kadar her satır kendi elimden çıktı."
        ),
        "stats": [
            {"value": "3", "label": "Kule Tipi"},
            {"value": "2", "label": "Düşman Türü"},
            {"value": "9+", "label": "Dalga"},
            {"value": "%100", "label": "Harici Motorsuz"},
        ],
        "stack": [
            "Java", "Swing (JFrame / JPanel)", "AWT — Graphics2D", "BufferedImage & Sprite Kesme",
            "Nesne Yönelimli Tasarım", "Kalıtım & Çokbiçimlilik", "Çoklu İş Parçacığı",
            "Olay Güdümlü Girdi Yönetimi", "Dosya G/Ç ile Kayıt/Yükleme",
        ],
        "sections": [
            {
                "heading": "Sahne tabanlı bir durum makinesi",
                "body": (
                    "Menü, Oynanıyor, Oyun Bitti ve Düzenleme (harita editörü) sahneleri arasındaki geçişi "
                    "ortak bir SahneMethotlari arayüzü üzerinden yöneten bir durum makinesi kurdum. Her sahne "
                    "kendi güncelleme ve çizim döngüsünden sorumlu; ortak akışı ise tek bir merkezden yönetiyorum."
                ),
            },
            {
                "heading": "İki iş parçacığı, tek tutarlı ritim",
                "body": (
                    "Oyun mantığının güncellenmesi (update) ile ekrana çizim (render) ayrı ritimlerde ilerliyor "
                    "ve tutarlı bir kare hızı için senkronize ediliyor. Bu, ilk kez bir çoklu iş parçacığı "
                    "problemini gerçek bir üründe uçtan uca çözdüğüm proje oldu."
                ),
            },
            {
                "heading": "Yönetici katmanına bölünmüş sorumluluklar",
                "body": (
                    "DalgaYoneticisi, DusmanYoneticisi, KuleYoneticisi, FuzeYoneticisi ve HaritaYoneticisi olmak "
                    "üzere her sorumluluk ayrı bir yönetici sınıfına bölünmüş durumda. Ork ve Yarasa gibi düşman "
                    "tipleri ortak bir Dusman sınıfından türetiliyor; kalıtım ve çokbiçimlilik sayesinde yeni bir "
                    "düşman eklemek tek bir alt sınıf yazmaktan ibaret."
                ),
            },
            {
                "heading": "Kendi harita editörüm",
                "body": (
                    "Karo (tile) tabanlı bir düzenleyici yazdım: başlangıç/bitiş noktaları ve yol serbestçe "
                    "döşenip kendi dosya formatımda diske kaydediliyor, YukleKaydet sınıfı üzerinden tekrar "
                    "yükleniyor. Ekran görüntülerindeki harita da bu editörle tasarlandı."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/oyunum/combat.jpg", "alt": "Dalga 9/9 — okçu ve topçu kuleleri orklara ve yarasalara karşı"},
            {"src": "portfolio/img/oyunum/editor.png", "alt": "Karo tabanlı kendi harita editörüm"},
        ],
        "highlights": [
            "Sahne tabanlı durum makinesi", "Çoklu iş parçacığı", "Kendi harita editörü",
            "Kalıtım tabanlı düşman sistemi", "Dosyaya kayıt/yükleme", "Sprite tabanlı animasyon",
        ],
        "closing": (
            "Karmaşık bir sistemi birbirinden bağımsız ve test edilebilir parçalara ayırarak inşa etmek — "
            "bu projenin bana kazandırdığı en kalıcı yetkinlik oldu."
        ),
    },
    {
        "slug": "kitchen-rush",
        "name": "Kitchen Rush",
        "kind": "Unity Oyunu",
        "tagline": "Tek kişilik geliştirilmiş, gerçek zamanlı baskı üzerine kurulu bir mutfak simülasyonu.",
        "accent": "#ff7a45",
        "year": "Unity Solo Proje",
        "cover": "portfolio/img/kitchenrush/kitchen-1.jpg",
        "summary": (
            "Overcooked'tan ilham alan, tek oyunculu bir mutfak yönetim oyunu. Oyuncu istasyonlar arasında "
            "koşuyor, malzemeyi kesiyor, pişiriyor ve siparişleri süresi dolmadan tezgaha yetiştirmeye "
            "çalışıyor. Unity'de baştan sona tek başıma geliştirdim."
        ),
        "originality": (
            "Bu oyundaki tüm 3B modeller, dokular, animasyonlar ve arayüz tasarımı hazır bir asset paketi "
            "ya da üçüncü parti bir araç kullanılmadan, sıfırdan kendi ellerimle üretildi."
        ),
        "stats": [
            {"value": "17", "label": "C# Script"},
            {"value": "6", "label": "Etkileşimli İstasyon"},
            {"value": "7", "label": "NUnit Birim Testi"},
            {"value": "19", "label": "PBR Dokulu Prop"},
        ],
        "stack": [
            "Unity (Built-in RP)", "C#", "Yeni Input System", "NUnit", "Coroutine Tabanlı Zamanlama",
            "MaterialPropertyBlock", "Mixamo Rig Hattı", "Özel Editor Araçları", "TextMeshPro",
        ],
        "sections": [
            {
                "heading": "Oynanış döngüsü",
                "body": (
                    "Sipariş üretimi, malzeme hazırlama (kesme/pişirme), teslim ve puanlama arasında sürekli "
                    "bir baskı döngüsü kurdum: siparişler arası 15 saniye, ocakta pişirme süresi 3 saniye — "
                    "oyuncuyu sürekli hareket halinde tutacak şekilde ince ayarlandı."
                ),
            },
            {
                "heading": "IInteractable üzerinden kod mimarisi",
                "body": (
                    "Kesme tezgahı, ocak, lavabo gibi 6 farklı istasyon tipi tek bir IInteractable arayüzünü "
                    "paylaşıyor. Yeni bir istasyon eklemek, bu arayüzü uygulayan bir sınıf yazmaktan ibaret — "
                    "oynanış kodu istasyonun somut tipini hiç bilmiyor."
                ),
            },
            {
                "heading": "Girdi, hareket ve animasyon",
                "body": (
                    "Unity'nin yeni Input System'i ile 3 girdi eylemi (Move, Interact, Cut) tanımlandı. "
                    "Karakter, Mixamo rig hattından beslenen 2 durumlu (Idle/Walking) bir Animator ile hareket "
                    "ediyor."
                ),
            },
            {
                "heading": "Sızıntısız görsel geri bildirim",
                "body": (
                    "Vurgulama (highlight) sistemini shader'dan bağımsız kurmak için MaterialPropertyBlock "
                    "kullandım — her karede yeni materyal örneği oluşturup bellek sızdırmak yerine, GPU'ya "
                    "tek seferde yazılan hafif bir katman. Pişirme süresi gibi zamanlamalar da coroutine "
                    "tabanlı çalışıyor."
                ),
            },
            {
                "heading": "Test ve kalite güvencesi",
                "body": (
                    "16 çalışma zamanı scriptinin yanında 7 NUnit birim testi yazdım; bu, bir Unity "
                    "projesinde test kültürünü ilk kez ciddiye aldığım proje oldu."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/kitchenrush/kitchen-1.jpg", "alt": "Mutfak istasyonları ve hazırlanmış malzemeler"},
            {"src": "portfolio/img/kitchenrush/kitchen-2.jpg", "alt": "Şef karakteri mutfağın ortasında"},
        ],
        "highlights": [
            "IInteractable mimarisi", "Yeni Input System", "MaterialPropertyBlock",
            "Coroutine zamanlama", "NUnit test paketi", "Mixamo animasyon hattı",
        ],
        "closing": (
            "Arayüz öncelikli tasarım ve editör + çalışma-zamanı çift tetikli otomasyon kararları, "
            "küçük bir oyunda bile temiz mimarinin nasıl fark yarattığını gösterdi."
        ),
    },
    {
        "slug": "rage-attack",
        "name": "Rage Attack",
        "kind": "Unity Oyunu",
        "tagline": "Bir sinek, bir köy, tam bir sinir krizi — sistemik yapay zeka üzerine kurulu bir kaos oyunu.",
        "accent": "#b25cff",
        "year": "Unity Solo Proje",
        "cover": "portfolio/img/rageattack/village-overview.jpg",
        "summary": (
            "Oyuncu, bir karasinek (ya da sonradan açılan robotik bir sinek olan RobotSinek) rolünde bütün "
            "bir köyün üzerine üşüşüyor. Sahnede dolaşan her NPC bağımsız bir stres simülasyonu çalıştırıyor "
            "ve beş aşamalı bir öfke durum makinesiyle hafif rahatsızlıktan silahlanıp saldırmaya kadar "
            "tırmanıyor. Asıl mühendislik problemi, bu simülasyonu canlı ve kontrolden çıkmış hissettirmekti."
        ),
        "originality": (
            "Köydeki binalardan karakter animasyonlarına, prosedürel VFX'ten arayüze kadar bu oyundaki tüm "
            "tasarım ve animasyonlar hazır bir asset paketi ya da üçüncü parti bir araç kullanılmadan "
            "tarafımca üretildi."
        ),
        "stats": [
            {"value": "5", "label": "Aşamalı Öfke FSM'i"},
            {"value": "2", "label": "Oynanabilir Karakter"},
            {"value": "90+", "label": "Özel Editor Aracı"},
            {"value": "0", "label": "Sahnelenmiş Setpiece"},
        ],
        "stack": [
            "Unity (URP)", "C#", "NavMesh AI", "Yeni Input System", "TextMeshPro", "ScriptableObject",
            "Özel Editor Araçları", "Coroutine Durum Makineleri", "Prosedürel VFX",
            "Çalışma Zamanında Üretilen Arayüz", "Mixamo Rig Hattı", "PlayerPrefs",
        ],
        "sections": [
            {
                "heading": "Sinir Yönetimi: beş aşamalı NPC öfke FSM'i",
                "body": (
                    "Köydeki her karakter kendi öfke durum makinesini çalıştırıyor: hafif rahatsızlıktan "
                    "başlayıp tetiklenme, panik ve nihayet silahlanıp saldırıya kadar beş aşamada tırmanıyor. "
                    "Sahnelenmiş bir setpiece yok — her kavga, her isyan, her kaçışma bu bağımsız durum "
                    "makinelerinin birbirini ve oyuncuyu görmesinin yan etkisi."
                ),
            },
            {
                "heading": "İki oynanabilir karakter, tamamen farklı yetenek setleri",
                "body": (
                    "Varsayılan çevik sinek ile oyun içi öldürme sayısına bağlı olarak açılan daha güçlü "
                    "RobotSinek arasında seçim yapılabiliyor; ikisinin de yetenek kiti baştan sona farklı."
                ),
            },
            {
                "heading": "XP, kart tabanlı yükseltmeler ve roguelite ilerleme",
                "body": (
                    "Köylüleri tahrik ederek kazanılan XP, oyun içinde kart tabanlı yetenek seçimleri açan bir "
                    "seviye atlama sistemine besleniyor. Kart arayüzü hazır bir UI paketi değil, tamamen kod "
                    "üzerinden çalışma zamanında üretiliyor."
                ),
            },
            {
                "heading": "NavMesh tabanlı AI ve prosedürel VFX",
                "body": (
                    "Köylülerin hareketi NavMesh üzerinde çözülüyor; hastalık yayılması, panik ve misilleme "
                    "gibi efektlerin çoğu Shuriken editöründen değil, doğrudan kod üzerinden büyütülen "
                    "prosedürel VFX ile üretiliyor."
                ),
            },
            {
                "heading": "90'dan fazla özel Editor aracı",
                "body": (
                    "Köyü sahne içinde hızlıca kurup dengelemek için 90'ın üzerinde özel Unity Editor aracı "
                    "yazdım — tek başına geliştirdiğim bir projede en çok zaman kazandıran yatırım, oynanışın "
                    "kendisi kadar bu araçlar oldu."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/rageattack/village-overview.jpg", "alt": "Köyün genel görünümü"},
            {"src": "portfolio/img/rageattack/village-stressbars.jpg", "alt": "Köylülerin canlı stres/öfke barları"},
            {"src": "portfolio/img/rageattack/card-selection.jpg", "alt": "Kart tabanlı yetenek seçim ekranı"},
            {"src": "portfolio/img/rageattack/editor-scenebuild.jpg", "alt": "Özel Editor araçlarıyla sahne kurulumu"},
        ],
        "highlights": [
            "5 aşamalı öfke FSM'i", "NavMesh AI", "Prosedürel VFX", "90+ Editor aracı",
            "Kart tabanlı roguelite ilerleme", "Çalışma zamanında üretilen arayüz",
        ],
        "closing": (
            "Emergent kaosu scriptlenmiş setpiece'lere tercih etmek — her NPC'yi stres, görüş hattı ve "
            "birbirleriyle etkileşime tepki veren bağımsız bir ajan gibi tasarlamak — bu projenin çekirdeği."
        ),
    },
    {
        "slug": "belgelendirme",
        "name": "Belgelendirme",
        "kind": "Web Platformu (PHP)",
        "tagline": "Bilinçli olarak framework'süz yazılmış bir belgelendirme & tetkik yönetim platformu.",
        "accent": "#6c63ff",
        "year": "Web Uygulaması",
        "cover": "portfolio/img/belgelendirme/dashboard.jpg",
        "summary": (
            "Bir belgelendirme/denetim kuruluşunun tüm iş akışını yöneten uçtan uca bir platform: firma "
            "kayıtları, sertifika yaşam döngüsü, tetkik planlama, masraf takibi ve raporlama tek bir sistemde "
            "birleşiyor. Laravel ya da Symfony yerine bilinçli olarak ham PDO ve prosedürel PHP tercih edildi."
        ),
        "stats": [
            {"value": "20", "label": "MySQL Tablosu"},
            {"value": "5", "label": "Kullanıcı Rolü"},
            {"value": "0", "label": "PHP Framework"},
            {"value": "2", "label": "Composer Kütüphanesi"},
        ],
        "stack": [
            "PHP 8 (Prosedürel)", "PDO — Gerçek Prepared Statement", "MySQL", "Composer",
            "Monolog 3.9", "PHPMailer 6.10", "Vanilla JavaScript", "AJAX Mini-SPA Sayfalar",
        ],
        "sections": [
            {
                "heading": "Bilinçli olarak framework'süz",
                "body": (
                    "Laravel ya da Symfony yerine ham PDO, prosedürel PHP ve elle yazılmış vanilla JS "
                    "bileşenlerini tercih ettim. Her sayfa kendi AJAX uç noktalarını barındıran, bağımsız "
                    "çalışabilen küçük bir \"mini-SPA\". Amaç: paylaşımlı hosting üzerinde minimum bağımlılıkla "
                    "hızlıca devreye alınabilen, tek bir mühendisin kafasında tamamen taşıyabileceği bir sistem "
                    "kurmaktı."
                ),
            },
            {
                "heading": "Beş rol, tek oturum modeli",
                "body": (
                    "Admin, muhasebe yöneticisi, denetçi ve danışman gibi beş farklı rol tek bir oturum ve "
                    "güvenlik çekirdeğinden yönetiliyor; kontrol paneli girilen role göre kendini yeniden "
                    "yönlendiriyor."
                ),
            },
            {
                "heading": "Sertifikanın yaşam döngüsü",
                "body": (
                    "Firma sicilinden başlayıp akreditasyon türü, belgelendiren kuruluş, belge numarası, "
                    "kapsam ve geçerlilik tarihleri üzerinden ilerleyen tam bir belgelendirme yaşam döngüsü "
                    "yönetimi kurdum — belge takip kulesi süresi yaklaşan/geçen belgeleri anında işaretliyor."
                ),
            },
            {
                "heading": "Veri katmanı",
                "body": (
                    "MySQL üzerinde 20 tablo, çoğunlukla 3NF ve seçici foreign key'lerle tasarlandı. Kanıt "
                    "dosyaları için ikili (LONGBLOB) depolama, sık okunan sayaçlar için ise performans "
                    "amaçlı denormalize alanlar kullanıldı."
                ),
            },
            {
                "heading": "Tetkik planlama, masraf takibi ve raporlama",
                "body": (
                    "Denetçi sahası üzerinden tetkik planlama, danışman/eğitim masraflarının ve tahsilatların "
                    "takibi, uyumluluk raporlama paneli ve Monolog + PHPMailer ile çalışan bir yazışma "
                    "motoru — hepsi aynı oturum ve yetkilendirme çekirdeği üzerine kuruldu."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/belgelendirme/dashboard.jpg", "alt": "Role göre yönlendirilen kontrol paneli"},
            {"src": "portfolio/img/belgelendirme/tracking.jpg", "alt": "Belge takip kulesi — süresi yaklaşan/geçen belgeler"},
        ],
        "highlights": [
            "Framework'süz mimari", "5 rol / tek oturum", "20 tablolu MySQL şeması",
            "LONGBLOB kanıt depolama", "Monolog + PHPMailer", "AJAX tabanlı mini-SPA sayfalar",
        ],
        "closing": (
            "Karmaşık bir iş süreci illa büyük bir framework gerektirmez — doğru katmanlanmış prosedürel "
            "PHP, paylaşımlı hosting kısıtları altında bile sürdürülebilir bir sistem kurabiliyor."
        ),
    },
]


# Every fixed word the templates say on their own, so a template never has to
# ask which language it is in.
UI = {
    "nav_home": "Ana Sayfa",
    "nav_cv": "Özgeçmiş",
    "nav_projects": "Projelerim",
    "nav_contact": "İletişim",
    "site_description": "Uğur Polat — Bilgisayar Mühendisi, oyun ve web geliştirici. Java, Unity, PHP ve Django projeleri.",
    "footer": "Django ile sıfırdan inşa edildi.",
    "title_home": "Ana Sayfa",
    "cta_projects": "Projelerimi Gör",
    "cta_contact": "İletişime Geç",
    "scroll_cue": "aşağı kaydır ↓",
    "kicker_about": "Hakkımda",
    "heading_about": "Kim bu Uğur?",
    "kicker_education": "Eğitim",
    "heading_education": "Okul Bilgileri",
    "kicker_skills": "Bildiğim Diller & Araçlar",
    "heading_skills": "Yetenekler",
    "kicker_work": "Yaptıklarım",
    "heading_work": "Uçtan Uca Geliştirdiğim 4 Proje",
    "kicker_contact": "İletişim",
    "heading_contact": "Konuşalım",
    "contact_prose": "Yeni fırsatlara ve iş birliklerine açığım — aşağıdaki kanallardan ulaşabilirsin.",
    "title_projects": "Projelerim",
    "projects_eyebrow": "Böbürlenme zamanı",
    "projects_tagline": "İki Unity oyunu, framework'süz bir PHP platformu ve elle yazılmış bir Java oyunu — dördü de sıfırdan, uçtan uca.",
    "card_cta": "İncele →",
    "originality_label": "Orijinallik notu:",
    "kicker_stack": "Kullanılan Teknolojiler",
    "kicker_gallery": "Ekran Görüntüleri",
    "back_to_projects": "← Tüm Projeler",
}

CONTENT = {
    "tr": {"profile": PROFILE, "projects": PROJECTS, "ui": UI},
    "en": {"profile": data_en.PROFILE, "projects": data_en.PROJECTS, "ui": data_en.UI},
}


def site(lang):
    """The whole content set for a language, falling back to Turkish."""
    return CONTENT.get(lang, CONTENT["tr"])


def get_project(lang, slug):
    for project in site(lang)["projects"]:
        if project["slug"] == slug:
            return project
    return None
