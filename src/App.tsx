import React, { useState, useEffect } from "react";
import { 
  Trophy, 
  Flame, 
  Clock, 
  Search, 
  Sparkles, 
  Bell, 
  BellOff, 
  Globe, 
  Check, 
  Trash2, 
  TrendingUp, 
  Share2, 
  Play, 
  Volume2, 
  RotateCw,
  Zap,
  ChevronRight,
  ChevronLeft,
  Calendar,
  AlertCircle
} from "lucide-react";
import { Match, MatchEvent, League, NewsItem, LiveAlert } from "./types";

const translations = {
  ar: {
    appName: "KORA",
    appSub: "HUB",
    home: "الرئيسية",
    leagues: "الدوريات",
    matches: "المباريات",
    news: "الأخبار",
    liveAlerts: "تنبيهات مباشرة",
    matchSchedule: "جدول المباريات",
    showAll: "عرض الكل",
    roundStats: "إحصائيات الجولة الحية",
    goalsScoredThisWeek: "هدفاً تم تسجيلها في الدوريات الكبرى هذا الأسبوع",
    topScorer: "المتصدر الحالي",
    pointsAbbr: "ن",
    playedAbbr: "لعب",
    wonAbbr: "ف",
    drawnAbbr: "ت",
    lostAbbr: "خ",
    pointsFull: "نقاط",
    interactiveAlerts: "تنبيهات مخصصة حية",
    alertsDesc: "فعل التنبيهات لتصلك أهداف ومستجدات المقابلات تلقائياً.",
    enableAlerts: "روابط التنبيهات",
    disableAlerts: "تعطيل",
    clearAlerts: "مسح السجل",
    leagueStandings: "ترتيب الدوريات العالمية",
    selectLeague: "اختر بطولة",
    live: "مباشر",
    finished: "انتهت",
    notStarted: "لم تبدأ",
    all: "جميع المباريات",
    aiAnalysisBtn: "تحليل تكتيكي فوري بالذكاء الاصطناعي",
    aiAnalyzing: "تحليل تكتيكي ذكي بواسطة Gemini...",
    aiAuthor: "تقرير ذكي فوري",
    generateNewsBtn: "صياغة خبر عاجل بالذكاء الاصطناعي",
    newsPlaceholder: "مثال: انتقال محمد صلاح، أزمة ليفربول، صفقة كبرى...",
    generate: "توليد ونشر",
    generating: "جاري الصياغة الفنية...",
    noAlerts: "لا توجد تنبيهات جديدة حالياً. انتظر تقدم ديربيات البث التلقائي.",
    goalAlert: "هدف مباشر!",
    redCardAlert: "بطاقة حمراء!",
    matchStarted: "انطلاق المباراة!",
    matchEnded: "نهاية المباراة!",
    statsTitle: "لوحة التحكم بالأهداف التجريبية",
    simulationSubtitle: "تحديث تلقائي كل 8 ثوانٍ",
    forceGoalHome: "تسجيل هدف للأرض ⚽",
    forceGoalAway: "تسجيل هدف للضيف ⚽",
    latestEvents: "الوقائع والأحداث الميدانية",
    upcoming: "القادمة",
    loading: "تحميل بث البيانات التلقائي...",
    tacticalTip: "تغطية تكتيكية شاملة",
    viewReport: "توليد تقرير تكتيكي",
    noMatches: "لا توجد مباريات جارية حالياً",
    team: "الفريق",
    points: "النقاط",
    goalDiff: "الفرق",
    selectMatchTip: "انقر على أي مباراة في القائمة الجانبية لعرض تفاصيلها والتحليل المباشر لها هنا في لوحة التحكم الأساسية.",
    searchPlaceholder: "ابحث في الأخبار المنتشرة..."
  },
  en: {
    appName: "KORA",
    appSub: "HUB",
    home: "Home",
    leagues: "Leagues",
    matches: "Matches",
    news: "News",
    liveAlerts: "Live Alerts",
    matchSchedule: "Match Schedule",
    showAll: "Show All",
    roundStats: "Live Round Stats",
    goalsScoredThisWeek: "goals recorded across major leagues this week",
    topScorer: "Current Leader",
    pointsAbbr: "PTS",
    playedAbbr: "P",
    wonAbbr: "W",
    drawnAbbr: "D",
    lostAbbr: "L",
    pointsFull: "Points",
    interactiveAlerts: "Togglable Live Alerts",
    alertsDesc: "Enable browser alert simulation to catch football outcomes instantly.",
    enableAlerts: "Notify Me",
    disableAlerts: "Turn Off",
    clearAlerts: "Clear Logs",
    leagueStandings: "League Standings",
    selectLeague: "Select League",
    live: "LIVE",
    finished: "FINISHED",
    notStarted: "NOT STARTED",
    all: "All Matches",
    aiAnalysisBtn: "Gemini Tactical Analysis",
    aiAnalyzing: "Analyzing with Gemini...",
    aiAuthor: "Instant AI Report",
    generateNewsBtn: "Write AI News via Prompt",
    newsPlaceholder: "e.g., Mo Salah contract speculation, Haaland buyout...",
    generate: "Generate & Insert",
    generating: "Generating...",
    noAlerts: "No hot events yet. Automatic background simulation is running.",
    goalAlert: "LIVE Goal!",
    redCardAlert: "Red Card!",
    matchStarted: "Match Kickoff!",
    matchEnded: "Full Time!",
    statsTitle: "Event Simulator Control UI",
    simulationSubtitle: "Live updates active (auto-cycles)",
    forceGoalHome: "Force Home Goal ⚽",
    forceGoalAway: "Force Away Goal ⚽",
    latestEvents: "Detailed Historical Match Events",
    upcoming: "Upcoming",
    loading: "Fetching real-time soccer stream...",
    tacticalTip: "AI Tactical Commentary",
    viewReport: "Generate Analysis",
    noMatches: "No matches running right now",
    team: "Team",
    points: "Points",
    goalDiff: "GD",
    selectMatchTip: "Click on any match card in the tracker column to analyze it, toggle custom simulations, or fetch expert AI briefings.",
    searchPlaceholder: "Search global soccer news..."
  }
};

export default function App() {
  const [lang, setLang] = useState<"ar" | "en">("ar");
  const [matches, setMatches] = useState<Match[]>([]);
  const [leagues, setLeagues] = useState<League[]>([]);
  const [news, setNews] = useState<NewsItem[]>([]);
  const [alerts, setAlerts] = useState<LiveAlert[]>([]);
  const [selectedMatchId, setSelectedMatchId] = useState<string | null>(null);
  
  // States for user interaction
  const [activeLeagueTab, setActiveLeagueTab] = useState<string>("epl");
  const [matchFilter, setMatchFilter] = useState<"ALL" | "LIVE" | "FINISHED" | "NOT_STARTED">("ALL");
  const [newsSearch, setNewsSearch] = useState<string>("");
  const [alertsEnabled, setAlertsEnabled] = useState<boolean>(true);
  
  // AI query states
  const [aiReport, setAiReport] = useState<string | null>(null);
  const [isAiLoading, setIsAiLoading] = useState<boolean>(false);
  const [customNewsPrompt, setCustomNewsPrompt] = useState<string>("");
  const [isNewsGenerating, setIsNewsGenerating] = useState<boolean>(false);
  
  // Manual goals simulation states
  const [manualScorerEn, setManualScorerEn] = useState<string>("");
  const [manualScorerAr, setManualScorerAr] = useState<string>("");

  const t = translations[lang];
  const isRtl = lang === "ar";

  const getLeagueName = (leagueId: string) => {
    const l = leagues.find(x => x.id === leagueId);
    if (!l) return "";
    return lang === "ar" ? l.nameAr : l.nameEn;
  };

  // Data Polling
  const fetchData = async () => {
    try {
      const [matchesRes, leaguesRes, newsRes, alertsRes] = await Promise.all([
        fetch("/api/matches"),
        fetch("/api/leagues"),
        fetch("/api/news"),
        fetch("/api/alerts")
      ]);

      const mData = await matchesRes.json();
      const lData = await leaguesRes.json();
      const nData = await newsRes.json();
      const aData = await alertsRes.json();

      setMatches(mData);
      setLeagues(lData);
      setNews(nData);
      setAlerts(aData);

      // Pre-select live match as hero container if nothing selected yet
      if (mData.length > 0 && !selectedMatchId) {
        const liveMatch = mData.find((m: Match) => m.status === "LIVE");
        if (liveMatch) {
          setSelectedMatchId(liveMatch.id);
        } else {
          setSelectedMatchId(mData[0].id);
        }
      }
    } catch (err) {
      console.error("Error drawing live sports stream:", err);
    }
  };

  useEffect(() => {
    fetchData();
    // Poll every 3 seconds to keep UI incredibly live and synchronized with simulation
    const interval = setInterval(fetchData, 3000);
    return () => clearInterval(interval);
  }, [selectedMatchId]);

  // Handle AI analysis request
  const fetchTacticalAnalysis = async (matchId: string) => {
    setIsAiLoading(true);
    setAiReport(null);
    try {
      const res = await fetch("/api/ai/analyze-match", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ matchId, language: lang })
      });
      const data = await res.json();
      setAiReport(data.analysis);
    } catch (err) {
      console.error("AI Analysis failed:", err);
      setAiReport(lang === "ar" ? "تعذر الاتصال بالذكاء الاصطناعي حالياً." : "Failed to generate tactical analysis.");
    } finally {
      setIsAiLoading(false);
    }
  };

  // Generate Custom AI News
  const handleGenerateCustomNews = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!customNewsPrompt.trim()) return;
    
    setIsNewsGenerating(true);
    try {
      const res = await fetch("/api/ai/custom-news", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ topic: customNewsPrompt, language: lang })
      });
      const data = await res.json();
      if (data.success) {
        setCustomNewsPrompt("");
        // Instantly fetch all data to grab new article
        fetchData();
      }
    } catch (err) {
      console.error("Generating custom news article failed:", err);
    } finally {
      setIsNewsGenerating(false);
    }
  };

  // Force Goal manual simulation
  const handleForceGoal = async (matchId: string, team: "home" | "away") => {
    try {
      const playerEn = manualScorerEn.trim() || (team === "home" ? "Super Sub" : "Impact Player");
      const playerAr = manualScorerAr.trim() || (team === "home" ? "البديل السوبر" : "اللاعب الحاسم");
      
      const res = await fetch(`/api/matches/${matchId}/force-goal`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ team, playerEn, playerAr })
      });
      const data = await res.json();
      if (data.success) {
        setManualScorerEn("");
        setManualScorerAr("");
        fetchData();
        // Speak custom confirmation
        if (alertsEnabled) {
          const title = lang === "ar" ? data.alert.titleAr : data.alert.titleEn;
          const body = lang === "ar" ? data.alert.descAr : data.alert.descEn;
          showWebNotification(title, body);
        }
      }
    } catch (err) {
      console.error("Custom goal forced trigger failed:", err);
    }
  };

  // Clear Alerts History
  const handleClearAlerts = async () => {
    try {
      await fetch("/api/alerts/clear", { method: "POST" });
      setAlerts([]);
    } catch (e) {
      console.error("Failed clearing history log:", e);
    }
  };

  // Simple Notification mechanism
  const showWebNotification = (title: string, body: string) => {
    if (!("Notification" in window)) return;
    if (Notification.permission === "granted") {
      new Notification(title, { body, icon: "⚽" });
    } else if (Notification.permission !== "denied") {
      Notification.requestPermission().then(permission => {
        if (permission === "granted") {
          new Notification(title, { body, icon: "⚽" });
        }
      });
    }
  };

  // Request browser Notification permissions
  useEffect(() => {
    if (alertsEnabled && "Notification" in window) {
      Notification.requestPermission();
    }
  }, [alertsEnabled]);

  // Find currently selected match details
  const currentMatch = matches.find(m => m.id === selectedMatchId) || matches[0];

  // Filters news list based on search Input
  const filteredNews = news.filter(item => {
    const title = lang === "ar" ? item.titleAr : item.titleEn;
    const desc = lang === "ar" ? item.summaryAr : item.summaryEn;
    const query = newsSearch.toLowerCase();
    return title.toLowerCase().includes(query) || desc.toLowerCase().includes(query);
  });

  // Calculate dynamic statistics
  const totalLeaguesCount = leagues.length;
  const currentLiveCount = matches.filter(m => m.status === "LIVE").length;
  const activeLeague = leagues.find(l => l.id === activeLeagueTab);

  // Approximate goals scored globally this week (simulated based on standings)
  const totalGoalsThisWeek = leagues.reduce((acc, league) => {
    return acc + league.standings.reduce((sum, s) => sum + s.goalsFor, 0);
  }, 0);

  return (
    <div 
      id="main-app-container"
      className="min-h-screen bg-slate-950 text-slate-100 flex flex-col p-4 md:p-6 font-sans selection:bg-emerald-500 selection:text-slate-950 transition-all duration-300"
      dir={isRtl ? "rtl" : "ltr"}
    >
      {/* HEADER SECTION WITH DESIGN SYSTEM PARINGS */}
      <header className="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b border-slate-900 pb-5" id="app-header">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 bg-emerald-500 text-slate-950 rounded-xl flex items-center justify-center font-extrabold text-2xl shadow-lg shadow-emerald-500/15 animate-pulse">
            ⚽
          </div>
          <div>
            <h1 className="text-2xl font-black tracking-tight leading-none">
              {t.appName}<span className="text-emerald-500">{t.appSub}</span>
            </h1>
            <p className="text-[11px] text-slate-400 font-medium tracking-wider mt-1 uppercase">
              {lang === "ar" ? "التغطية الشاملة والمحاكاة الذكية" : "Universal Soccer Hub & Live Simulations"}
            </p>
          </div>
        </div>

        {/* Global Navigation - Non-functional decorative anchors to maintain Bento style alignment */}
        <nav className="hidden md:flex gap-6 text-sm font-semibold opacity-90 text-slate-300" id="main-navigation">
          <span className="hover:text-emerald-400 transition-colors cursor-pointer border-b-2 border-emerald-500 pb-1">{t.home}</span>
          <span className="hover:text-emerald-400 transition-colors cursor-pointer opacity-60">{t.leagues}</span>
          <span className="hover:text-emerald-400 transition-colors cursor-pointer opacity-60">{t.matches}</span>
          <span className="hover:text-emerald-400 transition-colors cursor-pointer opacity-60">{t.news}</span>
        </nav>

        {/* Controller and Language Badges */}
        <div className="flex flex-wrap items-center gap-3" id="header-tools">
          {/* Quick Alert Status Banner */}
          <button 
            onClick={() => setAlertsEnabled(!alertsEnabled)}
            id="alerts-toggle-btn"
            className={`px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-2 border transition-all duration-300 ${
              alertsEnabled 
                ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/30" 
                : "bg-slate-900 text-slate-400 border-slate-800"
            }`}
          >
            <div className={`w-2.5 h-2.5 rounded-full ${alertsEnabled ? "bg-emerald-500 animate-ping" : "bg-slate-600"}`}></div>
            <span>{alertsEnabled ? t.liveAlerts : `${t.liveAlerts} (${t.disableAlerts})`}</span>
          </button>

          {/* Bilingual Switcher */}
          <div className="flex bg-slate-900 p-1 rounded-full border border-slate-800" id="language-switcher">
            <button 
              onClick={() => setLang("ar")} 
              id="lang-ar-btn"
              className={`px-3.5 py-1.5 rounded-full text-xs font-extrabold transition-all duration-300 ${lang === "ar" ? "bg-emerald-500 text-slate-950 font-black" : "text-slate-400 hover:text-white"}`}
            >
              عربي
            </button>
            <button 
              onClick={() => setLang("en")} 
              id="lang-en-btn"
              className={`px-3.5 py-1.5 rounded-full text-xs font-extrabold transition-all duration-300 ${lang === "en" ? "bg-emerald-500 text-slate-950 font-black" : "text-slate-400 hover:text-white"}`}
            >
              EN
            </button>
          </div>
        </div>
      </header>

      {/* COMPACT INTERACTIVE RUNNING ALERTS CAROUSEL / LOG LINE */}
      {alerts.length > 0 && alertsEnabled && (
        <div className="mb-5 bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900 border border-emerald-500/30 rounded-2xl p-3 flex items-center justify-between text-xs gap-3 animate-fade-in" id="live-scrolling-ticker">
          <div className="flex items-center gap-3 flex-1 overflow-hidden">
            <span className="flex h-2 w-2 relative">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
            </span>
            <div className="font-bold text-red-400 uppercase tracking-wider shrink-0">
              {lang === "ar" ? "تنبيه مباشر" : "Live Alert"}:
            </div>
            <div className="truncate font-semibold text-slate-300">
              {lang === "ar" ? alerts[0].titleAr : alerts[0].titleEn} — {lang === "ar" ? alerts[0].descAr : alerts[0].descEn}
            </div>
          </div>
          <span className="text-[10px] text-slate-400 shrink-0 font-mono">
            {new Date(alerts[0].timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
          </span>
        </div>
      )}

      {/* MAIN BENTO GRID ARCHITECTURE */}
      <main className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 flex-grow" id="bento-grid-root">
        
        {/* ==========================================
            CARD 1: CURRENT LIVE MATCH / HERO (Cols 2, Rows 2)
            ========================================== */}
        <div className="md:col-span-2 md:row-span-2 bg-gradient-to-br from-slate-900 to-slate-950 rounded-[2.5rem] p-6 lg:p-8 border border-slate-800 relative overflow-hidden flex flex-col justify-between shadow-2xl min-h-[460px]" id="hero-match-card">
          
          {/* Top Line Info */}
          <div className="flex justify-between items-center mb-4">
            <div className="flex items-center gap-2">
              <span className={`px-3 py-1 rounded-full text-[10px] font-extrabold tracking-widest text-white uppercase flex items-center gap-1 ${
                currentMatch?.status === 'LIVE' 
                  ? 'bg-red-600 animate-pulse' 
                  : currentMatch?.status === 'FINISHED'
                    ? 'bg-slate-800'
                    : 'bg-indigo-600'
              }`}>
                {currentMatch?.status === 'LIVE' ? `${t.live} - ${currentMatch.minute}'` : currentMatch?.status === 'FINISHED' ? t.finished : t.notStarted}
              </span>
              <span className="text-xs bg-slate-800 px-2.5 py-1 rounded-lg text-slate-300 font-semibold uppercase tracking-wider">
                {currentMatch ? getLeagueName(currentMatch.leagueId) : ""}
              </span>
            </div>
            <div className="flex items-center gap-1.5 text-xs text-slate-400 font-mono">
              <Clock className="w-3.5 h-3.5 text-emerald-500" />
              <span>{lang === "ar" ? currentMatch?.timeAr : currentMatch?.timeEn}</span>
            </div>
          </div>

          {/* Teams and Global Big Score */}
          {currentMatch ? (
            <div className="bg-slate-950/40 p-5 rounded-3xl border border-slate-800/60 my-auto flex flex-col justify-center" id="hero-scores-wrapper">
              <div className="grid grid-cols-3 items-center text-center">
                
                {/* Home Team */}
                <div className="flex flex-col items-center space-y-3">
                  <div className="w-16 h-16 md:w-20 md:h-20 bg-slate-800/80 rounded-full border-4 border-slate-700/55 flex items-center justify-center text-3xl md:text-4xl shadow-md">
                    {currentMatch.homeLogo || "⚽"}
                  </div>
                  <div className="font-extrabold text-sm md:text-base tracking-tight text-white line-clamp-2">
                    {lang === "ar" ? currentMatch.homeTeamAr : currentMatch.homeTeamEn}
                  </div>
                </div>

                {/* Main Dynamic Score Digit */}
                <div className="flex flex-col items-center justify-center">
                  <div className="text-5xl md:text-6xl lg:text-7xl font-black tracking-tight flex gap-3 text-slate-100 font-mono drop-shadow-lg">
                    <span>{currentMatch.homeScore}</span>
                    <span className="opacity-30 animate-pulse">:</span>
                    <span>{currentMatch.awayScore}</span>
                  </div>
                  <span className="text-[10px] opacity-40 font-mono mt-2 tracking-widest uppercase">
                    {t.simulationSubtitle}
                  </span>
                </div>

                {/* Away Team */}
                <div className="flex flex-col items-center space-y-3">
                  <div className="w-16 h-16 md:w-20 md:h-20 bg-slate-800/80 rounded-full border-4 border-slate-700/55 flex items-center justify-center text-3xl md:text-4xl shadow-md">
                    {currentMatch.awayLogo || "⚪"}
                  </div>
                  <div className="font-extrabold text-sm md:text-base tracking-tight text-white line-clamp-2">
                    {lang === "ar" ? currentMatch.awayTeamAr : currentMatch.awayTeamEn}
                  </div>
                </div>

              </div>

              {/* Event Simulator Box inside Hero Match for deep interactive engagement */}
              <div className="mt-6 pt-5 border-t border-slate-900" id="manual-goal-injector">
                <div className="flex justify-between items-center mb-3">
                  <span className="text-[11px] font-bold text-slate-400 flex items-center gap-1.5 uppercase">
                    <Zap className="w-3.5 h-3.5 text-amber-500 animate-bounce" />
                    {t.statsTitle}
                  </span>
                  <span className="text-[10px] text-slate-500 font-mono bg-slate-900 px-2 py-0.5 rounded">
                    Admin sandbox sandbox
                  </span>
                </div>
                
                {/* Scoring Details Prompt */}
                <div className="grid grid-cols-2 gap-2 mb-3">
                  <input 
                    type="text"
                    placeholder={lang === "ar" ? "اسم المسجل (عربي)" : "Scorer name (EN)"}
                    value={lang === "ar" ? manualScorerAr : manualScorerEn}
                    onChange={(e) => {
                      if (lang === "ar") {
                        setManualScorerAr(e.target.value);
                      } else {
                        setManualScorerEn(e.target.value);
                      }
                    }}
                    className="bg-slate-950/90 text-slate-200 border border-slate-800 px-3 py-1.5 rounded-xl text-xs focus:outline-none focus:border-emerald-500 transition-colors placeholder:text-slate-600"
                  />
                  <div className="text-[10px] text-slate-400 flex items-center px-1">
                    {lang === "ar" ? "اكتب اسم الهداف واضغط إضافة سريعة للنتيجة" : "Type scorer name and click below to manifest goal."}
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <button 
                    onClick={() => handleForceGoal(currentMatch.id, "home")}
                    className="w-full bg-slate-900 border border-slate-800 hover:border-emerald-500 hover:bg-emerald-500/10 text-slate-300 py-2 rounded-xl font-bold text-xs transition-all duration-300 shadow-sm"
                  >
                    ⚽ {lang === "ar" ? `هدف لـ ${currentMatch.homeTeamAr}` : `Goal for ${currentMatch.homeTeamEn}`}
                  </button>
                  <button 
                    onClick={() => handleForceGoal(currentMatch.id, "away")}
                    className="w-full bg-slate-900 border border-slate-800 hover:border-emerald-500 hover:bg-emerald-500/10 text-slate-300 py-2 rounded-xl font-bold text-xs transition-all duration-300 shadow-sm"
                  >
                    ⚽ {lang === "ar" ? `هدف لـ ${currentMatch.awayTeamAr}` : `Goal for ${currentMatch.awayTeamEn}`}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className="py-20 text-center text-slate-500 font-medium">{t.noMatches}</div>
          )}

          {/* Events Log in Game / Timeline details matches */}
          {currentMatch && currentMatch.events && currentMatch.events.length > 0 && (
            <div className="mt-4 bg-slate-950/70 p-4 rounded-2xl border border-slate-800/50" id="match-live-events">
              <span className="text-[10px] font-extrabold text-slate-400 block mb-2 uppercase tracking-wide">
                🔥 {t.latestEvents}
              </span>
              <div className="max-h-24 overflow-y-auto space-y-1.5 pr-1 text-xs">
                {currentMatch.events.map((event, i) => (
                  <div key={i} className="flex items-center gap-2 border-b border-slate-900/40 pb-1 last:border-0 last:pb-0">
                    <span className="font-mono font-black text-emerald-400 bg-slate-900 px-1.5 py-0.5 rounded text-[10px]">
                      {event.minute}'
                    </span>
                    <span className="text-amber-500 text-sm">
                      {event.type === "goal" ? "⚽" : event.type === "card_yellow" ? "🟨" : event.type === "card_red" ? "🟥" : "🔄"}
                    </span>
                    <span className="font-bold text-slate-200">
                      {lang === "ar" ? event.playerAr : event.playerEn}
                    </span>
                    <span className="text-slate-400 opacity-85 text-[10px] truncate">
                      ({lang === "ar" ? event.detailAr : event.detailEn})
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* AI GEMINI TACTICAL COMMENTARY BOX (Live analysis drawer button) */}
          {currentMatch && (
            <div className="mt-5 pt-3 border-t border-slate-800/80" id="gemini-analysis-box">
              <button 
                onClick={() => fetchTacticalAnalysis(currentMatch.id)}
                disabled={isAiLoading}
                className="w-full bg-emerald-500 text-slate-950 hover:bg-emerald-400 disabled:bg-slate-800 disabled:text-slate-500 py-3 rounded-2xl font-extrabold text-xs tracking-tight flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 active:translate-y-px transition-all duration-300"
              >
                <Sparkles className="w-4 h-4 text-emerald-950 animate-bounce" />
                <span>{isAiLoading ? t.aiAnalyzing : t.aiAnalysisBtn}</span>
              </button>

              {aiReport && (
                <div className="mt-4 p-4 bg-emerald-950/15 border border-emerald-500/20 rounded-2xl relative animate-fade-in" id="ai-tactical-report">
                  <div className="absolute top-3 right-3 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider text-emerald-400">
                    <Sparkles className="w-3 h-3" />
                    {t.aiAuthor}
                  </div>
                  <h4 className="text-xs font-black text-emerald-400 tracking-wide mb-1 opacity-90 uppercase">
                    {t.tacticalTip}
                  </h4>
                  <p className="text-xs text-slate-200 leading-relaxed font-medium">
                    {aiReport}
                  </p>
                </div>
              )}
            </div>
          )}
        </div>


        {/* ==========================================
            CARD 2: MATCH SCHEDULE & SELECTOR (Col 1, Rows 2)
            ========================================== */}
        <div className="md:col-span-1 md:row-span-2 bg-emerald-500 text-slate-950 rounded-[2.5rem] p-6 flex flex-col justify-between shadow-xl min-h-[460px]" id="schedule-bento-card">
          <div>
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-black tracking-tight flex items-center gap-2">
                <Calendar className="w-5 h-5" />
                {t.matchSchedule}
              </h3>
              <span className="text-[10px] font-extrabold bg-slate-950 text-white px-2.5 py-0.5 rounded-full uppercase">
                {matches.length} {t.matches}
              </span>
            </div>

            {/* Match Status Tabs */}
            <div className="grid grid-cols-4 bg-slate-950/10 p-0.5 rounded-xl border border-white/10 mb-4" id="match-filters">
              {(["ALL", "LIVE", "FINISHED", "NOT_STARTED"] as const).map((tab) => (
                <button
                  key={tab}
                  onClick={() => setMatchFilter(tab)}
                  className={`text-[9px] font-extrabold py-1.5 rounded-lg transition-transform text-center ${
                    matchFilter === tab 
                      ? "bg-slate-950 text-white font-black scale-105" 
                      : "text-slate-900 opacity-70 hover:opacity-100"
                  }`}
                >
                  {tab === "ALL" ? t.all.split(" ")[0] : tab === "LIVE" ? t.live : tab === "FINISHED" ? t.finished.split(" ")[0] : t.upcoming}
                </button>
              ))}
            </div>

            {/* List scrollbar */}
            <div className="space-y-3 max-h-[380px] overflow-y-auto pr-1" id="matches-scrollbar">
              {matches
                .filter(m => matchFilter === "ALL" ? true : m.status === matchFilter)
                .map((match) => {
                  const isSelected = match.id === selectedMatchId;
                  return (
                    <div 
                      key={match.id}
                      onClick={() => setSelectedMatchId(match.id)}
                      className={`p-3.5 rounded-2xl cursor-pointer transition-all duration-300 flex flex-col gap-1.5 ${
                        isSelected 
                          ? "bg-slate-950 text-slate-100 ring-2 ring-white/15 shadow-md scale-[1.02]" 
                          : "bg-white/20 hover:bg-white/30 text-slate-950"
                      }`}
                    >
                      <div className="flex justify-between items-center text-[10px] font-extrabold opacity-80 uppercase tracking-widest">
                        <span className="flex items-center gap-1">
                          {match.status === "LIVE" && <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>}
                          {match.status === "LIVE" ? `${t.live} - ${match.minute}'` : match.status === "FINISHED" ? t.finished : t.notStarted}
                        </span>
                        <span>{getLeagueName(match.leagueId)}</span>
                      </div>
                      
                      <div className="flex justify-between items-center font-black text-sm">
                        <span className="truncate max-w-[42%]">{lang === "ar" ? match.homeTeamAr : match.homeTeamEn}</span>
                        <span className="bg-slate-950/15 px-2 py-0.5 rounded font-mono text-xs font-black">
                          {match.status === "NOT_STARTED" ? (lang === "ar" ? match.timeAr : match.timeEn) : `${match.homeScore} - ${match.awayScore}`}
                        </span>
                        <span className="truncate max-w-[42%] text-right">{lang === "ar" ? match.awayTeamAr : match.awayTeamEn}</span>
                      </div>
                    </div>
                  );
                })}

              {matches.filter(m => matchFilter === "ALL" ? true : m.status === matchFilter).length === 0 && (
                <div className="text-center py-10 opacity-60 text-xs font-bold uppercase">{t.noMatches}</div>
              )}
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-white/10 text-[10px] text-slate-950 font-bold opacity-75 text-center leading-tight">
            {t.selectMatchTip}
          </div>
        </div>


        {/* ==========================================
            CARD 3: LIVE STATISTICS & NUMBER SUMMARY (Col 1, Row 1)
            ========================================== */}
        <div className="bg-indigo-600 text-white rounded-[2rem] p-6 relative overflow-hidden flex flex-col justify-between shadow-lg" id="stats-counter-card">
          <div className="relative z-10">
            <h4 className="text-xs font-bold uppercase tracking-wider opacity-75 mb-2">{t.roundStats}</h4>
            <div className="text-5xl font-black tracking-tight font-mono">{totalGoalsThisWeek}</div>
            <p className="text-xs opacity-90 mt-2 font-medium">
              {t.goalsScoredThisWeek} (Premier League, La Liga, UCL, Serie A)
            </p>
          </div>
          <div className="absolute -right-6 -bottom-6 text-9x1 text-white/15 font-mono select-none font-black translate-x-4 translate-y-4">
            ⚽
          </div>
          <div className="mt-4 pt-3 border-t border-white/10 flex justify-between items-center text-[10px] font-bold">
            <span>{lang === "ar" ? "قيد التحديث" : "Real-time updates"}</span>
            <span className="bg-white/15 px-2 py-0.5 rounded-full">LIVE</span>
          </div>
        </div>


        {/* ==========================================
            CARD 4: CURRENT LEADER CARD (Col 1, Row 1)
            ========================================== */}
        <div className="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 flex flex-col justify-between shadow-md" id="current-leader-card">
          <div className="flex items-center gap-3.5">
            <div className="w-12 h-12 bg-slate-800 rounded-2xl flex items-center justify-center text-3xl shadow-inner">
              🏆
            </div>
            <div>
              <div className="text-xs opacity-60 font-semibold">{t.topScorer}</div>
              <div className="font-extrabold text-base text-white">
                {lang === "ar" ? "ريال مدريد / السيتي" : "Real Madrid / Chelsea"}
              </div>
            </div>
          </div>

          <div className="my-3 space-y-1">
            <div className="flex justify-between text-[11px] font-semibold text-slate-400">
              <span>{lang === "ar" ? "معدل الحسم الكلي" : "Global win probability tracker"}</span>
              <span>85%</span>
            </div>
            <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
              <div className="bg-emerald-500 h-full w-[85%] rounded-full animate-pulse"></div>
            </div>
          </div>

          <div className="text-[10px] text-slate-400 font-medium">
            {lang === "ar" ? "إحصائيات مأخوذة بناء على آخر 38 جولة دوري ممتازة." : "Statistics computed automatically on current season cycles."}
          </div>
        </div>


        {/* ==========================================
            CARD 5: NEWS FEED & CUSTOM AI SPORTS NEWS GENERATOR (Cols 2, Row 1)
            ========================================== */}
        <div className="md:col-span-2 md:row-span-1 bg-slate-950 border border-slate-900 rounded-[2.5rem] p-6 flex flex-col justify-between gap-5 shadow-2xl" id="news-bento-card">
          
          <div className="flex-1 flex flex-col gap-4">
            <div className="flex flex-col md:flex-row justify-between md:items-center gap-3 border-b border-slate-900 pb-3" id="news-bar-header">
              <span className="text-base font-black tracking-tight uppercase flex items-center gap-2 text-white">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {t.news}
              </span>
              
              {/* Search feed bar */}
              <div className="relative">
                <input 
                  type="text" 
                  placeholder={t.searchPlaceholder}
                  value={newsSearch}
                  onChange={(e) => setNewsSearch(e.target.value)}
                  className="bg-slate-900 border border-slate-800 text-slate-200 px-3 pl-8 py-1 rounded-full text-xs focus:outline-none focus:border-emerald-500/40 w-full md:w-48 placeholder:text-slate-600"
                />
                <Search className={`w-3.5 h-3.5 text-slate-500 absolute top-2 ${isRtl ? 'left-3' : 'right-3'}`} />
              </div>
            </div>

            {/* News list viewport scrollable */}
            <div className="space-y-4 max-h-[160px] overflow-y-auto pr-1" id="news-scrollbar">
              {filteredNews.map((item) => (
                <div key={item.id} className="group flex gap-4 border-b border-slate-900 pb-4 last:border-0 last:pb-0" id={`news-item-${item.id}`}>
                  <div className="w-12 h-12 bg-slate-900 rounded-xl overflow-hidden flex items-center justify-center text-2.5xl shrink-0 border border-slate-800 shadow-inner group-hover:scale-110 transition-transform">
                    {item.image || "🗞️"}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="bg-indigo-500/10 text-indigo-400 text-[9px] font-black px-2 py-0.5 rounded uppercase">
                        {item.category}
                      </span>
                      <span className="text-[10px] text-slate-500 font-mono">
                        {new Date(item.publishedAt).toLocaleDateString()}
                      </span>
                    </div>
                    <h4 className="text-sm font-bold text-white leading-tight group-hover:text-emerald-400 transition-colors line-clamp-1">
                      {lang === "ar" ? item.titleAr : item.titleEn}
                    </h4>
                    <p className="text-xs text-slate-400 leading-snug line-clamp-2 mt-1 font-medium">
                      {lang === "ar" ? item.summaryAr : item.summaryEn}
                    </p>
                    <p className="text-[11px] text-slate-500 mt-1 lines-clamp-3 leading-relaxed hidden md:block">
                      {lang === "ar" ? item.contentAr : item.contentEn}
                    </p>
                  </div>
                </div>
              ))}
              
              {filteredNews.length === 0 && (
                <div className="text-center py-8 text-xs text-slate-600 font-bold uppercase">{lang === "ar" ? "لا تتوفر أخبار تطابق البحث" : "No sports news match query."}</div>
              )}
            </div>
          </div>

          {/* Dynamic Prompt Creator - write real news details in memory via AI */}
          <div className="bg-slate-900/40 p-4 rounded-3xl border border-slate-900" id="ai-custom-news-form">
            <h4 className="text-[11px] font-extrabold text-slate-400 mb-2 uppercase tracking-wide flex items-center gap-2">
              <Sparkles className="w-3.5 h-3.5 text-emerald-400 animate-spin" />
              {t.generateNewsBtn}
            </h4>
            
            <form onSubmit={handleGenerateCustomNews} className="flex flex-col md:flex-row gap-2">
              <input 
                type="text"
                placeholder={t.newsPlaceholder}
                value={customNewsPrompt}
                onChange={(e) => setCustomNewsPrompt(e.target.value)}
                className="bg-slate-950 text-slate-100 border border-slate-800 px-4 py-2 rounded-2xl flex-1 text-xs focus:outline-none focus:border-emerald-500 placeholder:text-slate-600"
              />
              <button 
                type="submit"
                disabled={isNewsGenerating || !customNewsPrompt.trim()}
                className="bg-indigo-600 text-white hover:bg-indigo-500 hover:scale-[1.01] disabled:bg-slate-800 disabled:text-slate-500 px-5 py-2 rounded-2xl font-extrabold text-xs transition-all duration-300 shadow-md flex items-center justify-center gap-2 shrink-0 border border-indigo-500/20"
              >
                <span>{isNewsGenerating ? t.generating : t.generate}</span>
              </button>
            </form>
          </div>

        </div>


        {/* ==========================================
            CARD 6: ALERT SWITCHBOX & FEED ARCHIVE (Col 1, Row 1)
            ========================================== */}
        <div className="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 flex flex-col justify-between shadow-xl min-h-[220px]" id="alerts-config-bento-card">
          <div>
            <div className="flex justify-between items-center mb-2">
              <h4 className="font-extrabold text-base text-slate-100 uppercase tracking-tight flex items-center gap-2">
                <Bell className="w-4 h-4 text-emerald-500" />
                {t.interactiveAlerts}
              </h4>
              {alerts.length > 0 && (
                <button 
                  onClick={handleClearAlerts}
                  className="text-[10px] text-slate-500 hover:text-red-400 font-extrabold flex items-center gap-1 transition-colors"
                  title="Clear history logs"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                  {t.clearAlerts}
                </button>
              )}
            </div>
            <p className="text-xs text-slate-400 mb-4 font-medium leading-snug">
              {t.alertsDesc}
            </p>
          </div>

          {/* Quick Active Logs */}
          <div className="flex-grow max-h-24 overflow-y-auto mb-4 pr-1" id="alerts-log-viewport">
            {alerts.length > 0 ? (
              <div className="space-y-2">
                {alerts.slice(0, 5).map((a, i) => (
                  <div key={a.id} className="p-2 bg-slate-950/40 rounded-xl border border-slate-800/80 text-[11px]">
                    <div className="flex justify-between font-bold text-slate-300 mb-0.5">
                      <span>{a.type === "goal" ? "⚽" : "🔴"} {lang === "ar" ? a.titleAr : a.titleEn}</span>
                      <span className="text-[9px] text-slate-500 font-mono">
                        {new Date(a.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </div>
                    <p className="text-slate-400 line-clamp-1">{lang === "ar" ? a.descAr : a.descEn}</p>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-6 text-slate-600 text-[11px] font-bold uppercase">{t.noAlerts}</div>
            )}
          </div>

          <div className="flex justify-between items-center bg-slate-950/40 p-2.5 rounded-2xl border border-slate-850" id="alerts-master-switch">
            <span className="text-[11px] font-bold text-slate-300 uppercase select-none">
              {alertsEnabled ? lang === "ar" ? "التنبيهات شغالة" : "Alerts enabled" : lang === "ar" ? "التنبيهات متوقفة" : "Alerts disabled"}
            </span>
            <div 
              onClick={() => setAlertsEnabled(!alertsEnabled)}
              className={`w-9 h-5 rounded-full p-0.5 cursor-pointer flex items-center transition-all duration-300 ${alertsEnabled ? "bg-emerald-500" : "bg-slate-700"}`}
            >
              <div className={`w-4 h-4 rounded-full bg-slate-950 transition-all duration-300 ${
                alertsEnabled ? (isRtl ? "mr-4" : "ml-4") : ""
              }`}></div>
            </div>
          </div>
        </div>


        {/* ==========================================
            CARD 7: SOCCER LEAGUES STANDINGS TABLES (Col 1, Row 1)
            ========================================== */}
        <div className="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 flex flex-col justify-between shadow-xl" id="standings-bento-card">
          <div>
            <div className="flex flex-col md:flex-row justify-between md:items-center gap-2 mb-4">
              <h4 className="text-xs font-black uppercase tracking-wider text-slate-400">
                ⭐ {t.leagueStandings}
              </h4>
              
              {/* League tabs selector */}
              <select 
                value={activeLeagueTab}
                onChange={(e) => setActiveLeagueTab(e.target.value)}
                className="bg-slate-950 text-slate-300 border border-slate-800 rounded-xl px-2 py-1 text-xs font-bold focus:outline-none focus:border-emerald-500"
                id="standings-league-picker"
              >
                {leagues.map((l) => (
                  <option key={l.id} value={l.id}>
                    {lang === "ar" ? l.nameAr : l.nameEn}
                  </option>
                ))}
              </select>
            </div>

            {/* League standings mini-table */}
            {activeLeague ? (
              <div className="space-y-2 max-h-[140px] overflow-y-auto pr-1" id="standings-scrollbar">
                
                {/* Standings Table Header */}
                <div className="grid grid-cols-12 text-[9px] font-extrabold text-slate-500 uppercase pb-1.5 border-b border-slate-850 px-1">
                  <span className="col-span-1">#</span>
                  <span className="col-span-7 text-left">{t.team}</span>
                  <span className="col-span-2 text-center">{t.playedAbbr}</span>
                  <span className="col-span-2 text-right">{t.pointsAbbr}</span>
                </div>

                {activeLeague.standings.map((team, idx) => (
                  <div 
                    key={idx} 
                    className="grid grid-cols-12 text-xs font-semibold items-center py-1.5 border-b border-slate-900/40 last:border-0 hover:bg-slate-950/30 px-1 rounded transition-colors"
                  >
                    <span className="col-span-1 font-mono font-black text-slate-400">
                      {team.rank}
                    </span>
                    <span className="col-span-7 flex items-center gap-1.5 truncate font-extrabold text-slate-200">
                      <span className="text-base shrink-0">{team.logo}</span>
                      <span className="truncate">{lang === "ar" ? team.teamAr : team.teamEn}</span>
                    </span>
                    <span className="col-span-2 text-center font-mono opacity-60">
                      {team.played}
                    </span>
                    <span className="col-span-2 text-right font-mono font-black text-emerald-400">
                      {team.points}{t.pointsAbbr}
                    </span>
                  </div>
                ))}

              </div>
            ) : (
              <div className="text-center py-6 text-slate-600 text-xs font-bold uppercase">{t.loading}</div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-800 flex justify-between items-center text-[10px] text-slate-500 font-bold uppercase">
            <span>{lang === "ar" ? "بث حي ومستمر" : "Continuous Live Stream"}</span>
            <span>{activeLeague ? `${activeLeague.countryAr || activeLeague.countryEn}` : ""}</span>
          </div>
        </div>

      </main>

      {/* FOOTER METADATA PARINGS */}
      <footer className="mt-8 border-t border-slate-900 pt-6 pb-2 text-center text-[11px] text-slate-500 font-semibold" id="app-footer-wrapper">
        <p className="flex justify-center items-center gap-2 flex-wrap">
          <span>&copy; 2026 KORAHUB Inc.</span>
          <span>•</span>
          <span className="text-emerald-500/80">{lang === "ar" ? "سحب تلقائي معزز بالذكاء الاصطناعي" : "Automated Live Sports feed powered by Gemini API"}</span>
          <span>•</span>
          <span>{lang === "ar" ? "محدث بالكامل بدقة عالية" : "All rights and simulations catalogued"}</span>
        </p>
      </footer>
    </div>
  );
}
