/**
 * Arabic string table — a faithful port of app/Lang/ar.php (primary audience).
 * The English (/en) locale is kept in the PHP app; porting it is the next
 * step and slots into the same `t()` API.
 */
export const ar: Record<string, string> = {
  'nav.matches': 'المباريات', 'nav.news': 'الأخبار', 'nav.videos': 'الفيديوهات',
  'nav.standings': 'الترتيب', 'nav.favorites': 'المفضلة', 'nav.more': 'المزيد',
  'nav.leagues': 'البطولات', 'nav.live': 'مباشر', 'nav.scorers': 'الهدافون',
  'nav.search': 'بحث', 'nav.home': 'الرئيسية',

  'day.today': 'اليوم', 'day.tomorrow': 'غداً', 'day.yesterday': 'أمس',

  'status.live': 'مباشر', 'status.finished': 'انتهت', 'status.notstarted': 'لم تبدأ',
  'status.halftime': 'استراحة', 'status.postponed': 'مؤجلة',

  'home.hero.title': 'مباريات اليوم بث مباشر',
  'home.hero.sub': 'نتائج مباشرة، مركز مباريات متكامل، ترتيب البطولات وأخبار عاجلة — كل ذلك في مكان واحد.',
  'home.hero.cta': 'مباريات اليوم', 'home.hero.cta2': 'المباريات المباشرة',
  'home.live': 'مباريات مباشرة الآن', 'home.today': 'مباريات اليوم',
  'home.featured': 'مباريات مميزة', 'home.top_leagues': 'أبرز البطولات',
  'home.trending_news': 'آخر الأخبار', 'home.highlights': 'ملخصات وفيديو',
  'home.popular_teams': 'فرق شائعة', 'home.standings': 'ترتيب البطولات',
  'home.top_scorers': 'الهدافون', 'home.stats': 'إحصائيات',
  'home.app_banner.title': 'قمهد لايف معك أينما كنت',
  'home.app_banner.sub': 'ثبّت التطبيق على هاتفك وتابع الأهداف لحظة وقوعها مع إشعارات فورية.',
  'home.app_banner.cta': 'تثبيت التطبيق',
  'home.newsletter.title': 'النشرة البريدية',
  'home.newsletter.sub': 'ملخص أسبوعي لأهم المباريات والأخبار — بدون إزعاج.',
  'home.newsletter.email': 'بريدك الإلكتروني', 'home.newsletter.cta': 'اشترك',
  'home.newsletter.done': 'تم الاشتراك بنجاح، أهلاً بك!', 'home.view_all': 'عرض الكل',

  'matches.title': 'جدول المباريات', 'matches.none': 'لا توجد مباريات في هذا اليوم',
  'matches.live_now': 'مباشر الآن', 'matches.pick_day': 'اختر اليوم',

  'standings.title': 'جدول الترتيب', 'standings.team': 'الفريق', 'standings.played': 'لعب',
  'standings.win': 'فاز', 'standings.draw': 'تعادل', 'standings.lose': 'خسر',
  'standings.gf': 'له', 'standings.ga': 'عليه', 'standings.gd': '+/-', 'standings.points': 'نقاط',
  'standings.none': 'جدول الترتيب غير متاح لهذه البطولة',

  'scorers.title': 'ترتيب الهدافين', 'scorers.player': 'اللاعب', 'scorers.team': 'الفريق',
  'scorers.goals': 'الأهداف', 'scorers.assists': 'صناعة الأهداف', 'scorers.pens': 'ركلات جزاء',
  'scorers.none': 'قائمة الهدافين غير متاحة',

  'leagues.title': 'البطولات والدوريات',
  'teams.title': 'الفرق', 'players.title': 'اللاعبون',

  'news.title': 'أخبار كرة القدم', 'news.latest': 'آخر الأخبار', 'news.related': 'أخبار ذات صلة',
  'news.read_more': 'اقرأ المزيد', 'news.source': 'المصدر', 'news.published': 'نُشر في',
  'news.partial': 'النص الكامل لهذا الخبر غير متاح حالياً.', 'news.read_source': 'اقرأ من المصدر',
  'news.prev': 'السابق', 'news.next': 'التالي', 'news.none': 'لا توجد أخبار حالياً',
  'news.search': 'ابحث في الأخبار…',

  'search.title': 'البحث', 'search.placeholder': 'ابحث عن فريق، بطولة، مباراة أو خبر…',
  'search.teams': 'الفرق', 'search.leagues': 'البطولات', 'search.matches': 'المباريات',
  'search.players': 'اللاعبون', 'search.news': 'الأخبار', 'search.none': 'لا توجد نتائج مطابقة',
  'search.hint': 'اكتب كلمة البحث في الأعلى للبدء',

  'fav.title': 'المفضلة',
  'fav.empty': 'لم تقم بإضافة أي عناصر إلى المفضلة بعد. اضغط على أيقونة النجمة بجانب أي فريق أو بطولة أو مباراة.',

  'footer.about': 'من نحن', 'footer.privacy': 'سياسة الخصوصية', 'footer.terms': 'شروط الاستخدام',
  'footer.contact': 'اتصل بنا', 'footer.rights': 'جميع الحقوق محفوظة',
  'footer.desc': 'منصة عربية متكاملة لمتابعة كرة القدم: نتائج مباشرة، مركز مباريات، ترتيب، هدافون وأخبار لحظة بلحظة.',
  'footer.sections': 'الأقسام', 'footer.legal': 'روابط', 'footer.follow': 'تابعنا',

  'videos.title': 'الفيديوهات',
  'videos.subtitle': 'ملخصات وأهداف أحدث المباريات، مقسّمة حسب البطولة',
  'videos.search': 'ابحث في الفيديوهات…', 'videos.none': 'لا توجد فيديوهات متاحة حالياً',
  'videos.no_match': 'لا توجد فيديوهات مطابقة لبحثك', 'videos.related': 'فيديوهات مشابهة',
  'videos.play': 'تشغيل الفيديو', 'videos.watch_default': 'ملخص المباراة',
  'videos.seo_h': 'فيديوهات وملخصات المباريات',
  'videos.seo_p1': 'قسم الفيديوهات في قمهد لايف يجمع لك أحدث ملخصات المباريات وأهدافها فور انتهائها: أهداف الدوريات الأوروبية الخمسة الكبرى، دوري أبطال أوروبا، الدوري السعودي والمصري، كأس العالم، وأبرز المنتخبات والأندية العربية والعالمية — كلها مقسّمة حسب البطولة ليسهل الوصول لما تبحث عنه.',
  'videos.seo_p2': 'شاهد اللقطات داخل الموقع مباشرة بجودة عالية، وابحث باسم الفريق أو البطولة أو المباراة، وتنقّل بين الصفحات لمتابعة أرشيف الملخصات القديمة. يتم تحديث القسم على مدار الساعة تلقائياً مع كل جولة مباريات جديدة.',

  'misc.min': 'دقيقة', 'misc.updated': 'آخر تحديث', 'misc.loading': 'جارٍ التحميل…',
  'misc.show_more': 'عرض المزيد', 'misc.retry': 'إعادة المحاولة', 'misc.error': 'حدث خطأ، حاول مجدداً',
  'misc.offline': 'أنت غير متصل بالإنترنت', 'misc.theme': 'الوضع الليلي', 'misc.share': 'مشاركة',
  'misc.copy_done': 'تم نسخ الرابط', 'misc.back': 'رجوع',
  'misc.days': 'يوم', 'misc.hours': 'ساعة', 'misc.minutes': 'دقيقة', 'misc.seconds': 'ثانية',
  'misc.am': 'ص', 'misc.pm': 'م', 'misc.notfound': 'الصفحة غير موجودة',
  'misc.notfound_sub': 'الرابط الذي طلبته غير موجود أو تم نقله.', 'misc.gohome': 'العودة للرئيسية',
  'misc.enable_notifications': 'تفعيل الإشعارات',
  'misc.update_ready': 'يوجد إصدار جديد من الموقع', 'misc.update_now': 'تحديث الآن',
  'misc.update_later': 'لاحقاً', 'misc.updating': 'جاري تحديث الموقع...',

  'page.about.title': 'من نحن', 'page.privacy.title': 'سياسة الخصوصية',
  'page.terms.title': 'شروط الاستخدام', 'page.contact.title': 'اتصل بنا',

  'wd.0': 'الأحد', 'wd.1': 'الاثنين', 'wd.2': 'الثلاثاء', 'wd.3': 'الأربعاء',
  'wd.4': 'الخميس', 'wd.5': 'الجمعة', 'wd.6': 'السبت',
  'mo.1': 'يناير', 'mo.2': 'فبراير', 'mo.3': 'مارس', 'mo.4': 'أبريل', 'mo.5': 'مايو',
  'mo.6': 'يونيو', 'mo.7': 'يوليو', 'mo.8': 'أغسطس', 'mo.9': 'سبتمبر', 'mo.10': 'أكتوبر',
  'mo.11': 'نوفمبر', 'mo.12': 'ديسمبر',
};

/** Translate a key, with optional {placeholder} interpolation. */
export function t(key: string, vars?: Record<string, string | number>): string {
  let s = ar[key] ?? key;
  if (vars) for (const k of Object.keys(vars)) s = s.replace(`{${k}}`, String(vars[k]));
  return s;
}
