import express from "express";
import path from "path";
import { createServer as createViteServer } from "vite";
import { GoogleGenAI } from "@google/genai";
import dotenv from "dotenv";

dotenv.config();

// Initialize Gemini API
const geminiApiKey = process.env.GEMINI_API_KEY;
let ai: GoogleGenAI | null = null;

if (geminiApiKey && geminiApiKey !== "MY_GEMINI_API_KEY") {
  try {
    ai = new GoogleGenAI({
      apiKey: geminiApiKey,
      httpOptions: {
        headers: {
          "User-Agent": "aistudio-build",
        },
      },
    });
    console.log("Gemini API initialized successfully client-side on backend.");
  } catch (err) {
    console.error("Failed to initialize Gemini API:", err);
  }
} else {
  console.log("Gemini API key not found or placeholder, running with fallback simulated AI reports.");
}

const app = express();
app.use(express.json());

const PORT = 3000;

// ==========================================
// SOCCER DATABASE AND MATCH SIMULATION ENGINE
// ==========================================

interface Standing {
  rank: number;
  teamEn: string;
  teamAr: string;
  played: number;
  won: number;
  drawn: number;
  lost: number;
  goalsFor: number;
  goalsAgainst: number;
  points: number;
  logo: string;
}

interface League {
  id: string;
  nameEn: string;
  nameAr: string;
  countryEn: string;
  countryAr: string;
  logo: string;
  standings: Standing[];
}

interface MatchEvent {
  minute: number;
  type: "goal" | "card_yellow" | "card_red" | "substitution";
  team: "home" | "away";
  playerEn: string;
  playerAr: string;
  detailEn: string;
  detailAr: string;
}

interface Match {
  id: string;
  leagueId: string;
  homeTeamEn: string;
  homeTeamAr: string;
  awayTeamEn: string;
  awayTeamAr: string;
  homeLogo: string;
  awayLogo: string;
  homeScore: number;
  awayScore: number;
  status: "LIVE" | "FINISHED" | "NOT_STARTED";
  minute: number;
  timeEn: string;
  timeAr: string;
  date: string; // e.g., YYYY-MM-DD
  events: MatchEvent[];
}

interface NewsItem {
  id: string;
  titleEn: string;
  titleAr: string;
  summaryEn: string;
  summaryAr: string;
  contentEn: string;
  contentAr: string;
  category: "news" | "analysis" | "transfer";
  image: string;
  publishedAt: string;
}

interface LiveAlert {
  id: string;
  matchId: string;
  leagueNameAr: string;
  leagueNameEn: string;
  homeTeamAr: string;
  homeTeamEn: string;
  awayTeamAr: string;
  awayTeamEn: string;
  type: "goal" | "red_card" | "match_start" | "match_end";
  titleAr: string;
  titleEn: string;
  descAr: string;
  descEn: string;
  timestamp: string;
}

// Global In-Memory Soccer Database
let leagues: League[] = [
  {
    id: "epl",
    nameEn: "Premier League",
    nameAr: "الدوري الإنجليزي الممتاز",
    countryEn: "England",
    countryAr: "إنجلترا",
    logo: "🏆",
    standings: [
      { rank: 1, teamEn: "Manchester City", teamAr: "مانشستر سيتي", played: 38, won: 28, drawn: 7, lost: 3, goalsFor: 96, goalsAgainst: 34, points: 91, logo: "🩵" },
      { rank: 2, teamEn: "Arsenal", teamAr: "أرسنال", played: 38, won: 28, drawn: 5, lost: 5, goalsFor: 91, goalsAgainst: 29, points: 89, logo: "🔴" },
      { rank: 3, teamEn: "Liverpool", teamAr: "ليفربول", played: 38, won: 24, drawn: 10, lost: 4, goalsFor: 86, goalsAgainst: 41, points: 82, logo: "💥" },
      { rank: 4, teamEn: "Aston Villa", teamAr: "أستون فيلا", played: 38, won: 20, drawn: 8, lost: 10, goalsFor: 76, goalsAgainst: 61, points: 68, logo: "🦁" },
      { rank: 5, teamEn: "Tottenham Hotspur", teamAr: "توتنهام", played: 38, won: 20, drawn: 6, lost: 12, goalsFor: 74, goalsAgainst: 61, points: 66, logo: "🐓" },
      { rank: 6, teamEn: "Chelsea", teamAr: "تشيلسي", played: 38, won: 18, drawn: 9, lost: 11, goalsFor: 77, goalsAgainst: 63, points: 63, logo: "🔵" },
      { rank: 7, teamEn: "Manchester United", teamAr: "مانشستر يونايتد", played: 38, won: 18, drawn: 6, lost: 14, goalsFor: 57, goalsAgainst: 58, points: 60, logo: "😈" },
    ]
  },
  {
    id: "laliga",
    nameEn: "La Liga",
    nameAr: "الدوري الإسباني",
    countryEn: "Spain",
    countryAr: "إسبانيا",
    logo: "🇪🇸",
    standings: [
      { rank: 1, teamEn: "Real Madrid", teamAr: "ريال مدريد", played: 38, won: 29, drawn: 8, lost: 1, goalsFor: 87, goalsAgainst: 26, points: 95, logo: "👑" },
      { rank: 2, teamEn: "Barcelona", teamAr: "برشلونة", played: 38, won: 26, drawn: 7, lost: 5, goalsFor: 79, goalsAgainst: 44, points: 85, logo: "🔵🔴" },
      { rank: 3, teamEn: "Girona", teamAr: "جيرونا", played: 38, won: 25, drawn: 6, lost: 7, goalsFor: 85, goalsAgainst: 46, points: 81, logo: "🔴⚪" },
      { rank: 4, teamEn: "Atletico Madrid", teamAr: "أتلتيكو مدريد", played: 38, won: 24, drawn: 4, lost: 10, goalsFor: 70, goalsAgainst: 43, points: 76, logo: "🐻" },
      { rank: 5, teamEn: "Athletic Club", teamAr: "أتلتيك بيلباو", played: 38, won: 19, drawn: 11, lost: 8, goalsFor: 61, goalsAgainst: 37, points: 68, logo: "🦁" },
    ]
  },
  {
    id: "ucl",
    nameEn: "Champions League",
    nameAr: "دوري أبطال أوروبا",
    countryEn: "Europe",
    countryAr: "أوروبا",
    logo: "⭐",
    standings: [
      { rank: 1, teamEn: "Bayern Munich", teamAr: "بايرن ميونخ", played: 6, won: 5, drawn: 1, lost: 0, goalsFor: 12, goalsAgainst: 6, points: 16, logo: "🔴" },
      { rank: 2, teamEn: "Paris Saint-Germain", teamAr: "باريس سان جيرمان", played: 6, won: 4, drawn: 1, lost: 1, goalsFor: 9, goalsAgainst: 4, points: 13, logo: "🗼" },
      { rank: 3, teamEn: "Inter Milan", teamAr: "إنتر ميلان", played: 6, won: 3, drawn: 3, lost: 0, goalsFor: 8, goalsAgainst: 5, points: 12, logo: "⚫🔵" },
      { rank: 4, teamEn: "Borussia Dortmund", teamAr: "بوروسيا دورتموند", played: 6, won: 3, drawn: 2, lost: 1, goalsFor: 7, goalsAgainst: 4, points: 11, logo: "🟡⚫" },
    ]
  },
  {
    id: "seriea",
    nameEn: "Serie A",
    nameAr: "الدوري الإيطالي",
    countryEn: "Italy",
    countryAr: "إيطاليا",
    logo: "🇮🇹",
    standings: [
      { rank: 1, teamEn: "Inter Milan", teamAr: "إنتر ميلان", played: 38, won: 29, drawn: 7, lost: 2, goalsFor: 89, goalsAgainst: 22, points: 94, logo: "🐍" },
      { rank: 2, teamEn: "AC Milan", teamAr: "ميلان", played: 38, won: 22, drawn: 9, lost: 7, goalsFor: 76, goalsAgainst: 49, points: 75, logo: "🔴⚫" },
      { rank: 3, teamEn: "Juventus", teamAr: "يوفنتوس", played: 38, won: 19, drawn: 14, lost: 5, goalsFor: 54, goalsAgainst: 31, points: 71, logo: "🦓" },
      { rank: 4, teamEn: "Atalanta", teamAr: "أتالانتا", played: 38, won: 21, drawn: 6, lost: 11, goalsFor: 72, goalsAgainst: 42, points: 69, logo: "🔵⚫" },
      { rank: 5, teamEn: "Bologna", teamAr: "بولونيا", played: 38, won: 18, drawn: 14, lost: 6, goalsFor: 54, goalsAgainst: 32, points: 68, logo: "🔴🔵" },
    ]
  }
];

// Seed Matches
let matches: Match[] = [
  // Live matches (simulation advances these)
  {
    id: "m1",
    leagueId: "epl",
    homeTeamEn: "Arsenal",
    homeTeamAr: "أرسنال",
    awayTeamEn: "Liverpool",
    awayTeamAr: "ليفربول",
    homeLogo: "🔴",
    awayLogo: "💥",
    homeScore: 1,
    awayScore: 1,
    status: "LIVE",
    minute: 65,
    timeEn: "LIVE COMMENTARY",
    timeAr: "مباشر الآن",
    date: "2026-05-26",
    events: [
      { minute: 18, type: "goal", team: "home", playerEn: "Bukayo Saka", playerAr: "بوكايو ساكا", detailEn: "Assist by Martin Ødegaard", detailAr: "صناعة من مارتن أوديغارد" },
      { minute: 42, type: "goal", team: "away", playerEn: "Mohamed Salah", playerAr: "محمد صلاح", detailEn: "Clinical finish after counter attack", detailAr: "إنهاء ممتاز بعد هجمة مرتدة" },
      { minute: 55, type: "card_yellow", team: "away", playerEn: "Alexis Mac Allister", playerAr: "أليكسيس ماك أليستر", detailEn: "Tactical foul", detailAr: "خطأ تكتيكي لوقف المرتدة" },
    ]
  },
  {
    id: "m2",
    leagueId: "laliga",
    homeTeamEn: "Real Madrid",
    homeTeamAr: "ريال مدريد",
    awayTeamEn: "Barcelona",
    awayTeamAr: "برشلونة",
    homeLogo: "👑",
    awayLogo: "🔵🔴",
    homeScore: 2,
    awayScore: 3,
    status: "LIVE",
    minute: 82,
    timeEn: "EL CLASICO",
    timeAr: "الكلاسيكو المثير",
    date: "2026-05-26",
    events: [
      { minute: 12, type: "goal", team: "away", playerEn: "Robert Lewandowski", playerAr: "روبرت ليفاندوفسكي", detailEn: "Powerful header from corner", detailAr: "رأسية قوية من ركنية" },
      { minute: 26, type: "goal", team: "home", playerEn: "Jude Bellingham", playerAr: "جود بيلينجهام", detailEn: "Stunning strike outside the box", detailAr: "تسديدة رائعة من خارج المنطقة" },
      { minute: 49, type: "goal", team: "away", playerEn: "Lamine Yamal", playerAr: "لامين يامال", detailEn: "Incredible solo curler into top corner", detailAr: "تسديدة لولبية يسارية في المقص" },
      { minute: 67, type: "goal", team: "home", playerEn: "Kylian Mbappé", playerAr: "كيليان مبابي", detailEn: "Penalty kick conversion", detailAr: "ركلة جزاء ناجحة" },
      { minute: 73, type: "goal", team: "away", playerEn: "Raphinha", playerAr: "رافينيا", detailEn: "Tap-in from close range", detailAr: "متابعة سهلة أمام شباك خالية" },
      { minute: 78, type: "card_red", team: "home", playerEn: "Antonio Rüdiger", playerAr: "أنطونيو روديجر", detailEn: "Dangerous slide tackle", detailAr: "تدخل عنيف بالقدمين" },
    ]
  },
  {
    id: "m3",
    leagueId: "seriea",
    homeTeamEn: "Juventus",
    homeTeamAr: "يوفنتوس",
    awayTeamEn: "AC Milan",
    awayTeamAr: "ميلان",
    homeLogo: "🦓",
    awayLogo: "🔴⚫",
    homeScore: 0,
    awayScore: 0,
    status: "LIVE",
    minute: 20,
    timeEn: "MILAN VS JUVENTUS",
    timeAr: "قمة إيطاليا",
    date: "2026-05-26",
    events: []
  },
  // Upcoming matches Today / Tomorrow
  {
    id: "u1",
    leagueId: "epl",
    homeTeamEn: "Manchester City",
    homeTeamAr: "مانشستر سيتي",
    awayTeamEn: "Chelsea",
    awayTeamAr: "تشيلسي",
    homeLogo: "🩵",
    awayLogo: "🔵",
    homeScore: 0,
    awayScore: 0,
    status: "NOT_STARTED",
    minute: 0,
    timeEn: "19:00",
    timeAr: "19:00",
    date: "2026-05-26",
    events: []
  },
  {
    id: "u2",
    leagueId: "ucl",
    homeTeamEn: "Bayern Munich",
    homeTeamAr: "بايرن ميونخ",
    awayTeamEn: "Paris Saint-Germain",
    awayTeamAr: "باريس سان جيرمان",
    homeLogo: "🔴",
    awayLogo: "🗼",
    homeScore: 0,
    awayScore: 0,
    status: "NOT_STARTED",
    minute: 0,
    timeEn: "21:45",
    timeAr: "21:45",
    date: "2026-05-26",
    events: []
  },
  {
    id: "u3",
    leagueId: "laliga",
    homeTeamEn: "Atletico Madrid",
    homeTeamAr: "أتلتيكو مدريد",
    awayTeamEn: "Athletic Club",
    awayTeamAr: "أتلتيك بيلباو",
    homeLogo: "🐻",
    awayLogo: "🦁",
    homeScore: 0,
    awayScore: 0,
    status: "NOT_STARTED",
    minute: 0,
    timeEn: "18:30",
    timeAr: "18:30",
    date: "2026-05-27", // tomorrow
    events: []
  },
  // Finished matches (Yesterday)
  {
    id: "f1",
    leagueId: "epl",
    homeTeamEn: "Manchester United",
    homeTeamAr: "مانشستر يونايتد",
    awayTeamEn: "Tottenham Hotspur",
    awayTeamAr: "توتنهام",
    homeLogo: "😈",
    awayLogo: "🐓",
    homeScore: 3,
    awayScore: 2,
    status: "FINISHED",
    minute: 90,
    timeEn: "FT",
    timeAr: "انتهت",
    date: "2026-05-25",
    events: [
      { minute: 10, type: "goal", team: "away", playerEn: "Son Heung-min", playerAr: "سون هيونغ مين", detailEn: "Stunning volley near post", detailAr: "تسديدة على الطائر رائعة" },
      { minute: 34, type: "goal", team: "home", playerEn: "Bruno Fernandes", playerAr: "برونو فيرنانديز", detailEn: "Free kick special", detailAr: "ركلة حرة مباشرة متقنة" },
      { minute: 58, type: "goal", team: "home", playerEn: "Marcus Rashford", playerAr: "ماركوس راشفورد", detailEn: "Solo run over left wing", detailAr: "مجهود فردي مميز واختراق رائع" },
      { minute: 70, type: "goal", team: "away", playerEn: "James Maddison", playerAr: "جيمس ماديسون", detailEn: "Penalty kick", detailAr: "ضربة جزاء" },
      { minute: 88, type: "goal", team: "home", playerEn: "Rasmus Højlund", playerAr: "راسموس هويلوند", detailEn: "Header in critical moments", detailAr: "رأسية قاتلة بالدقيقة الأخيرة" },
    ]
  },
  {
    id: "f2",
    leagueId: "laliga",
    homeTeamEn: "Girona",
    homeTeamAr: "جيرونا",
    awayTeamEn: "Real Sociedad",
    awayTeamAr: "ريال سوسيداد",
    homeLogo: "🔴⚪",
    awayLogo: "🔵⚪",
    homeScore: 2,
    awayScore: 0,
    status: "FINISHED",
    minute: 90,
    timeEn: "FT",
    timeAr: "انتهت",
    date: "2026-05-25",
    events: [
      { minute: 44, type: "goal", team: "home", playerEn: "Artem Dovbyk", playerAr: "أرتيم دوفبيك", detailEn: "Header", detailAr: "رأسية متقنة" },
      { minute: 81, type: "goal", team: "home", playerEn: "Viktor Tsyhankov", playerAr: "فيكتور تسيهانكوف", detailEn: "Shot from inside", detailAr: "تسديدة أرضية زاحفة" },
    ]
  }
];

// In-Memory Live Alerts (pollable list)
let alerts: LiveAlert[] = [
  {
    id: "a-init-1",
    matchId: "m1",
    leagueNameAr: "الدوري الإنجليزي الممتاز",
    leagueNameEn: "Premier League",
    homeTeamAr: "أرسنال",
    homeTeamEn: "Arsenal",
    awayTeamAr: "ليفربول",
    awayTeamEn: "Liverpool",
    type: "goal",
    titleAr: "هدف مباشر! محمد صلاح يسجل",
    titleEn: "Live Goal! Mohamed Salah scores",
    descAr: "ليفربول يسجل هدف التعادل 1-1 ضد أرسنال بالدقيقة 42 عبر محمد صلاح",
    descEn: "Mohamed Salah scores the equalizer for Liverpool (1-1) in the 42nd minute.",
    timestamp: new Date().toISOString()
  }
];

// Mock News Feed
let news: NewsItem[] = [
  {
    id: "n1",
    titleEn: "Salah on Fire: Egyptian King leads Liverpool's title fight",
    titleAr: "الملك المصري صلاح يقود هجوم ليفربول بروح قتالية عالية في سباق اللقب",
    summaryEn: "Salah continues to shine, scoring visual masterpieces and keeping Liverpool close in the Premier League table.",
    summaryAr: "يواصل النجم المصري محمد صلاح إبهار الجماهير بأهداف حاسمة تبقي ليفربول في دائرة التنافس الملتهب بالدوري في الجولات الأخيرة.",
    contentEn: "Mohamed Salah has been the vital catalyst for Liverpool this season, breaking modern goal-scoring records once again. His tactical importance under high-pressure fixtures proved instrumental in maintaining their position near the top. Fans and analysts alike celebrate his consistency and physical durability.",
    contentAr: "يقدم محمد صلاح موسماً استثنائياً جديداً مع ليفربول، محطماً عدة أرقام قياسية متميزة على مستوى التهديف وصناعة الألعاب. ويرى الخبراء الرياضيون أن التزامه التكتيكي وحضوره القوي في المواجهات الجماهيرية الصعبة يشكل الركيزة الأساسية لاستقرار ليفربول.",
    category: "news",
    image: "⚽",
    publishedAt: "2026-05-26T01:00:00Z"
  },
  {
    id: "n2",
    titleEn: "The Dream of El Clasico: Barcelona's Yamal stealing the limelight",
    titleAr: "بريق الكلاسيكو: الشاب لامين يامال يسرق الأضواء ويسحر الجماهير بصناعته للعب",
    summaryEn: "Lamine Yamal makes history as the most impressive display of soccer genius in the Spanish football classic clash.",
    summaryAr: "يستمر الفتى الذهبي لامين يامال في صناعة التاريخ بمهارات خرافية ميزت ديربي الكلاسيكو بمواجهته المباشرة مع مدافعي النادي الملكي.",
    contentEn: "At just 18 years of age, Barcelona's prodigy Lamine Yamal has taken the footballing world by storm. His latest match performance in Real Madrid's Santiago Bernabéu stadium showcased remarkable footballing maturity, precise ball control, and fearless offensive runs that leave elite defenders trailing.",
    contentAr: "بدخول سن الثامنة عشرة فقط، تحول موهبة برشلونة لامين يامال إلى حديث الصحافة العالمية بعد الفاصل الخيالي الذي قدمه في البرنابيو. تميز لعبه بالنضج العالي، التمريرات الحاسمة، والقدرة الفائقة على الاختراق دون أي خوف من ضغط جماهير الخصم.",
    category: "analysis",
    image: "🌟",
    publishedAt: "2026-05-26T00:30:00Z"
  },
  {
    id: "n3",
    titleEn: "Transfer Talk: Haaland heavily linked with stunning Summer moves",
    titleAr: "شائعات الانتقالات: هالاند يثير ضجة كبرى باحتمال انتقاله في الصيف القادم",
    summaryEn: "Reports suggest Spanish giants are ready to break the bank for Manchester City's clinical goal machine.",
    summaryAr: "تقارير إسبانية تفيد بأن عمالقة الدوري الإسباني يتأهبون لإقناع النجم النرويجي إيرلينغ هالاند بالانتقال الفلكي في الميركاتو المقبل.",
    contentEn: "Erling Haaland's future remains a dominant talking point across elite European networks. Scouts and directors are reportedly aligning huge financial instruments to capture the Norwegian striker, although Manchester City remains extremely firm on its long-term retention contracts.",
    contentAr: "ما زال مستقبل إيرلنغ هالاند يتصدر المشهد الإعلامي في أوروبا، حيث تتحدث الأنباء عن رغبة جارفة من كبار الأندية في تقديم عقود غير مسبوقة. لكن إدارة مانشستر سيتي تثق بقوة في بقاء المدمر النرويجي لإكمال مسيرته الأسطورية.",
    category: "transfer",
    image: "✈️",
    publishedAt: "2026-05-25T22:00:00Z"
  }
];

// ==========================================
// BACKGROUND AUTOMATED MATCH SIMULATION
// ==========================================

// Players names to generate random realistic events
const homePlayers = ["Odegaard", "Saka", "Martinelli", "Havertz", "Rice", "Gabriel", "Saliba", "Vinicius Jr", "Mbappe", "Bellingham", "Rodrygo", "Valverde", "Modric", "Vlahovic", "Chiesa", "Locatelli", "Yildiz"];
const homePlayersAr = ["أوديغارد", "ساكا", "مارتينيلي", "هافيرتز", "رايس", "غابرييل", "ساليبا", "فينيسيوس جونيور", "مبابي", "بيلينجهام", "رودريغو", "فالفيردي", "مودريتش", "فلاهوفيتش", "كييزا", "لوكاتيلي", "يلديز"];

const awayPlayers = ["Salah", "Diaz", "Jota", "Nunez", "Szoboszlai", "Mac Allister", "Van Dijk", "Lewandowski", "Lamine Yamal", "Raphinha", "Gavi", "Pedri", "De Jong", "Leao", "Pulisic", "Giroud", "Loftus-Cheek"];
const awayPlayersAr = ["صلاح", "دياز", "جوتا", "نونيز", "سوبوسلاي", "ماك أليستر", "فان دايك", "ليفاندوفسكي", "لامين يامال", "رافينيا", "غافي", "بيدري", "دي يونغ", "لياو", "بوليسيتش", "جيرو", "لوفتوس تشيك"];

function simulateLiveMatches() {
  matches.forEach((match) => {
    if (match.status !== "LIVE") return;

    // Advance minute
    match.minute += 1;

    // Match finishes at 90 minutes
    if (match.minute >= 91) {
      match.status = "FINISHED";
      match.minute = 90;
      match.timeEn = "FT";
      match.timeAr = "انتهت";

      // Trigger Alert
      const alertId = `a-${Date.now()}-end`;
      const endAlert: LiveAlert = {
        id: alertId,
        matchId: match.id,
        leagueNameAr: getLeagueName(match.leagueId, "ar"),
        leagueNameEn: getLeagueName(match.leagueId, "en"),
        homeTeamAr: match.homeTeamAr,
        homeTeamEn: match.homeTeamEn,
        awayTeamAr: match.awayTeamAr,
        awayTeamEn: match.awayTeamEn,
        type: "match_end",
        titleAr: "صافرة النهاية! انتهاء المباراة",
        titleEn: "Full Time! Match ended",
        descAr: `انتهت المباراة المثيرة: ${match.homeTeamAr} ${match.homeScore} - ${match.awayScore} ${match.awayTeamAr}`,
        descEn: `The thrilling match has ended: ${match.homeTeamEn} ${match.homeScore} - ${match.awayScore} ${match.awayTeamEn}`,
        timestamp: new Date().toISOString()
      };
      alerts.unshift(endAlert);

      // Dynamically update the standing table!
      updateLeagueStandingsAfterMatch(match);
      return;
    }

    // Small probability (10%) of a key event happening in each simulation step
    const rand = Math.random();
    if (rand < 0.12) {
      const eventTypeRand = Math.random();
      const isHome = Math.random() < 0.5;

      if (eventTypeRand < 0.55) {
        // GOAL!!!
        const playerIndex = Math.floor(Math.random() * homePlayers.length);
        const playerEn = isHome ? homePlayers[playerIndex] : awayPlayers[playerIndex];
        const playerAr = isHome ? homePlayersAr[playerIndex] : awayPlayersAr[playerIndex];

        if (isHome) match.homeScore += 1;
        else match.awayScore += 1;

        const newEvent: MatchEvent = {
          minute: match.minute,
          type: "goal",
          team: isHome ? "home" : "away",
          playerEn,
          playerAr,
          detailEn: isHome ? "Beautiful strike!" : "Brilliant finish!",
          detailAr: isHome ? "تسديدة غاية في الروعة" : "إنهاء رائع في الشباك"
        };
        match.events.push(newEvent);

        // Trigger Alert
        const alertId = `a-${Date.now()}-goal-${match.id}`;
        const goalAlert: LiveAlert = {
          id: alertId,
          matchId: match.id,
          leagueNameAr: getLeagueName(match.leagueId, "ar"),
          leagueNameEn: getLeagueName(match.leagueId, "en"),
          homeTeamAr: match.homeTeamAr,
          homeTeamEn: match.homeTeamEn,
          awayTeamAr: match.awayTeamAr,
          awayTeamEn: match.awayTeamEn,
          type: "goal",
          titleAr: `هدف مباشر! لـ ${isHome ? match.homeTeamAr : match.awayTeamAr}`,
          titleEn: `Live Goal! for ${isHome ? match.homeTeamEn : match.awayTeamEn}`,
          descAr: `أحرز اللاعب ${playerAr} هدفاً في الدقيقة ${match.minute}. النتيجة الآن: ${match.homeTeamAr} ${match.homeScore} - ${match.awayScore} ${match.awayTeamAr}`,
          descEn: `${playerEn} scored in the minute ${match.minute}. Score: ${match.homeTeamEn} ${match.homeScore} - ${match.awayScore} ${match.awayTeamEn}`,
          timestamp: new Date().toISOString()
        };
        alerts.unshift(goalAlert);

      } else if (eventTypeRand < 0.85) {
        // YELLOW CARD
        const playerIndex = Math.floor(Math.random() * homePlayers.length);
        const playerEn = isHome ? homePlayers[playerIndex] : awayPlayers[playerIndex];
        const playerAr = isHome ? homePlayersAr[playerIndex] : awayPlayersAr[playerIndex];

        const newEvent: MatchEvent = {
          minute: match.minute,
          type: "card_yellow",
          team: isHome ? "home" : "away",
          playerEn,
          playerAr,
          detailEn: "Foul yellow carded",
          detailAr: "بطاقة صفراء بسبب تدخل خشن"
        };
        match.events.push(newEvent);

      } else {
        // RED CARD!!
        const playerIndex = Math.floor(Math.random() * homePlayers.length);
        const playerEn = isHome ? homePlayers[playerIndex] : awayPlayers[playerIndex];
        const playerAr = isHome ? homePlayersAr[playerIndex] : awayPlayersAr[playerIndex];

        const newEvent: MatchEvent = {
          minute: match.minute,
          type: "card_red",
          team: isHome ? "home" : "away",
          playerEn,
          playerAr,
          detailEn: "Dangerous challenge, straight red card!",
          detailAr: "تدخّل متهور مباشر، بطاقة حمراء مستحقة"
        };
        match.events.push(newEvent);

        // Alert
        const alertId = `a-${Date.now()}-red-${match.id}`;
        const redAlert: LiveAlert = {
          id: alertId,
          matchId: match.id,
          leagueNameAr: getLeagueName(match.leagueId, "ar"),
          leagueNameEn: getLeagueName(match.leagueId, "en"),
          homeTeamAr: match.homeTeamAr,
          homeTeamEn: match.homeTeamEn,
          awayTeamAr: match.awayTeamAr,
          awayTeamEn: match.awayTeamEn,
          type: "red_card",
          titleAr: `طرد مباشر! بطاقة حمراء في قمة ${match.homeTeamAr} ضد ${match.awayTeamAr}`,
          titleEn: `Red Card! Expulsion in ${match.homeTeamEn} vs ${match.awayTeamEn}`,
          descAr: `تلقى اللاعب ${playerAr} بطاقة حمراء مباشرة بالدقيقة ${match.minute}`,
          descEn: `Player ${playerEn} received a straight red card in the ${match.minute}th minute.`,
          timestamp: new Date().toISOString()
        };
        alerts.unshift(redAlert);
      }
    }
  });

  // Automatically cycle FINISHED matches back to NOT_STARTED for loop simulation,
  // or start NOT_STARTED matches after some cycles to make the scheduler look incredibly live!
  const liveCount = matches.filter(m => m.status === "LIVE").length;
  if (liveCount === 0) {
    // restart the simulation cycle: turn finished matches into draft LIVE again with new random seed score
    matches.forEach(m => {
      if (m.id === "m1" || m.id === "m2" || m.id === "m3") {
        m.status = "LIVE";
        m.minute = Math.floor(Math.random() * 20) + 1; // Start randomly at 1-20 min
        m.homeScore = Math.floor(Math.random() * 2);
        m.awayScore = Math.floor(Math.random() * 2);
        m.events = [];
      }
    });

    // Seed a renewal message
    alerts.unshift({
      id: `a-restart-${Date.now()}`,
      matchId: "m1",
      leagueNameAr: "تحديث مباشر",
      leagueNameEn: "Live Update",
      homeTeamAr: "مباريات جديدة",
      homeTeamEn: "New Matches",
      awayTeamAr: "انطلقت لتوها",
      awayTeamEn: "Just Started",
      type: "match_start",
      titleEn: "Matches simulation restarted!",
      titleAr: "انطلاق مباريات الدوري والمحاكاة الحية من جديد!",
      descEn: "Leagues schedule has updated. New matches kicked off now.",
      descAr: "تم جدول مباريات جديدة على الهواء مباشرة، استمتع بالتغطية والتحليلات الآلية.",
      timestamp: new Date().toISOString()
    });
  }
}

function getLeagueName(leagueId: string, lang: "ar" | "en") {
  const league = leagues.find(l => l.id === leagueId);
  if (!league) return "";
  return lang === "ar" ? league.nameAr : league.nameEn;
}

function updateLeagueStandingsAfterMatch(match: Match) {
  const league = leagues.find(l => l.id === match.leagueId);
  if (!league) return;

  const home = league.standings.find(s => s.teamEn === match.homeTeamEn || s.teamAr === match.homeTeamAr);
  const away = league.standings.find(s => s.teamEn === match.awayTeamEn || s.teamAr === match.awayTeamAr);

  if (home && away) {
    home.played += 1;
    away.played += 1;
    home.goalsFor += match.homeScore;
    home.goalsAgainst += match.awayScore;
    away.goalsFor += match.awayScore;
    away.goalsAgainst += match.homeScore;

    if (match.homeScore > match.awayScore) {
      home.won += 1;
      home.points += 3;
      away.lost += 1;
    } else if (match.homeScore < match.awayScore) {
      away.won += 1;
      away.points += 3;
      home.lost += 1;
    } else {
      home.drawn += 1;
      away.drawn += 1;
      home.points += 1;
      away.points += 1;
    }

    // Sort standings
    league.standings.sort((a, b) => {
      if (b.points !== a.points) return b.points - a.points;
      const gdB = b.goalsFor - b.goalsAgainst;
      const gdA = a.goalsFor - a.goalsAgainst;
      if (gdB !== gdA) return gdB - gdA;
      return b.goalsFor - a.goalsFor;
    });

    // Re-assign ranks
    league.standings.forEach((s, idx) => {
      s.rank = idx + 1;
    });
  }
}

// Start Background Simulation immediately, runs every 8 seconds
setInterval(simulateLiveMatches, 8000);

// ==========================================
// API ENDPOINTS
// ==========================================

// Get All Leagues (including standing tables)
app.get("/api/leagues", (req, res) => {
  res.json(leagues);
});

// Get Matches (Live, Finished, Upcoming)
app.get("/api/matches", (req, res) => {
  res.json(matches);
});

// Trigger a new custom event (e.g. Force score update manually for UI testing)
app.post("/api/matches/:id/force-goal", (req, res) => {
  const matchId = req.params.id;
  const match = matches.find(m => m.id === matchId);
  if (!match) {
    return res.status(404).json({ error: "Match not found" });
  }

  const team = req.body.team || "home";
  const playerEn = req.body.playerEn || "Substitute Hero";
  const playerAr = req.body.playerAr || "البديل السوبر";

  if (team === "home") match.homeScore += 1;
  else match.awayScore += 1;

  const newEvent: MatchEvent = {
    minute: match.minute,
    type: "goal",
    team: team === "home" ? "home" : "away",
    playerEn,
    playerAr,
    detailEn: "Instant manual impact goal!",
    detailAr: "هدف خاطف فوري مباشر!"
  };
  match.events.push(newEvent);

  // Trigger alert
  const alertId = `a-manual-${Date.now()}`;
  const goalAlert: LiveAlert = {
    id: alertId,
    matchId: match.id,
    leagueNameAr: getLeagueName(match.leagueId, "ar"),
    leagueNameEn: getLeagueName(match.leagueId, "en"),
    homeTeamAr: match.homeTeamAr,
    homeTeamEn: match.homeTeamEn,
    awayTeamAr: match.awayTeamAr,
    awayTeamEn: match.awayTeamEn,
    type: "goal",
    titleAr: "هدف صاعق! بديل يفاجئ الجميع",
    titleEn: "Sensational Goal! Super sub scores",
    descAr: `سجل ${playerAr} هدفاً مفاجئاً لصالح ${team === "home" ? match.homeTeamAr : match.awayTeamAr}. النتيجة: ${match.homeTeamAr} ${match.homeScore} - ${match.awayScore} ${match.awayTeamAr}`,
    descEn: `${playerEn} scored amazing goal for ${team === "home" ? match.homeTeamEn : match.awayTeamEn}. Score: ${match.homeTeamEn} ${match.homeScore} - ${match.awayScore} ${match.awayTeamEn}`,
    timestamp: new Date().toISOString()
  };
  alerts.unshift(goalAlert);

  res.json({ success: true, match, alert: goalAlert });
});

// Get Live Notifications / Alerts
app.get("/api/alerts", (req, res) => {
  res.json(alerts.slice(0, 15)); // return top 15 alerts
});

// Clear Alerts
app.post("/api/alerts/clear", (req, res) => {
  alerts = [];
  res.json({ success: true });
});

// Get Sports News
app.get("/api/news", (req, res) => {
  res.json(news);
});

// Add dynamically simulated AI commentary or report using Gemini key!
// This aligns perfectly with server-side AI rules and provides automated expert predictions
app.post("/api/ai/analyze-match", async (req, res) => {
  const { matchId, language } = req.body;
  const isAr = language === "ar";

  const match = matches.find(m => m.id === matchId);
  if (!match) {
    return res.status(404).json({ error: isAr ? "المباراة غير موجودة" : "Match not found" });
  }

  const matchDetails = `
    League Name: ${match.leagueId}
    Home Team: ${match.homeTeamEn} (Arabic: ${match.homeTeamAr})
    Away Team: ${match.awayTeamEn} (Arabic: ${match.awayTeamAr})
    Current score: ${match.homeTeamEn} ${match.homeScore} - ${match.awayScore} ${match.awayTeamEn}
    Minute: ${match.minute}
    Match Status: ${match.status}
    Events so far: ${JSON.stringify(match.events)}
  `;

  if (ai) {
    try {
      const prompt = isAr 
        ? `بصفتك معلق ومحلل كروي مخضرم باللغة العربية، قم بصياغة تحليل تكتيكي سريع وممتع عن مباراة (${match.homeTeamAr} ضد ${match.awayTeamAr}) بناءً على التفاصيل الرياضية الحالية وصيغة النتيجة الكروية الحالية: ${match.homeScore} - ${match.awayScore} في الدقيقة ${match.minute}. اجعل التحليل واقعياً ومثيراً يتضمن ترجيح الفائز والسيناريو المتوقع وما يجب على كلا المدربين فعله في الشوط أو الدقائق المتبقية. لا تزد عن 150 كلمة.`
        : `As an expert football tactical analyst and sports caster, write a swift and exciting tactical commentary of the match (${match.homeTeamEn} vs ${match.awayTeamEn}) with current score: ${match.homeScore} - ${match.awayScore} in the ${match.minute}th minute. Provide manager breakdown advice and predict the logical winner scenario. Limit to 150 words. Do not use markdown tags other than bold text.`;

      const response = await ai.models.generateContent({
        model: "gemini-3.5-flash",
        contents: prompt,
        config: {
          temperature: 0.85,
        }
      });

      const analysisText = response.text || "تحليل غير متوفر حالياً.";
      return res.json({ analysis: analysisText, generatedByAI: true });

    } catch (err: any) {
      console.error("Gemini API call failed, backing up to simulation:", err);
      // Fallback response with beautiful simulation analysis in case of key error or quota issues
    }
  }

  // Beautiful fallback AI Simulation reports based on soccer events
  let simulatedReport = "";
  if (isAr) {
    if (match.homeScore > match.awayScore) {
      simulatedReport = `تحليل تكتيكي متميز: يفرض فريق ${match.homeTeamAr} أسلوبه الهجومي القائم على الاستحواذ الإيجابي في خط المنتصف بفضل تحركات البدلاء وقوة الدفع على الأطراف. يتميز دفاعهم بالصلابة أمام هجمات ${match.awayTeamAr}. على مدرب ${match.awayTeamAr} استغلال سرعات المهاجمين في العمق وربما تبديل الرسم التكتيكي إلى 4-3-3 قبل فوات الأوان.`;
    } else if (match.homeScore < match.awayScore) {
      simulatedReport = `رؤية فنية ذكية: يتألق لاعبو ${match.awayTeamAr} بالتزام دفاعي حديدي وهجمات مرتدة خاطفة شكلت خطورة قصوى على مرمى ${match.homeTeamAr}. الهدف الأخير أتى كترجمة واضحة للاختراقات الذكية. يتوجب على مدرب ${match.homeTeamAr} زيادة الكثافة العددية داخل الصندوق والضغط العالي لمنع ارتداد الكرة.`;
    } else {
      simulatedReport = `تقرير تكتيكي مغلق: تتسم هذه المباراة بالندية والتوازن الدفاعي الشديد من كلا الفريقين. يركز كل من المدربين على تفادي الأخطاء في مناطق البناء، مما جعل اللعب هادئاً نسبياً مع انحسار الكرة في الوسط. من المرجح أن تحسم ركلة ثابتة أو خطأ فردي نتيجة اللقاء لصالح أحد الطرفين.`;
    }
  } else {
    if (match.homeScore > match.awayScore) {
      simulatedReport = `Tactical Analysis: ${match.homeTeamEn} is currently dictating the tempo through high pressing and smooth possession sequences. ${match.awayTeamEn} suffers from poor transition spacing in the middle. The visiting coach must introduce speed on the wings to stretch the home defenses before time runs out.`;
    } else if (match.homeScore < match.awayScore) {
      simulatedReport = `Tactical Analysis: A masterclass in counter-attacking football by ${match.awayTeamEn}. They converted chances clinicaly and maintained supreme shape discipline in low block. ${match.homeTeamEn}'s midfielders need to play through balls verticaly to stand a chance of cracking this defensive wall.`;
    } else {
      simulatedReport = `Tactical Analysis: A highly matches duel of tight spaces. Both sides prioritize tactical security in low block. Midfield battles are intense and passing lanes are blocked. It will take a set-piece gem or individual brilliance to break this stalemate.`;
    }
  }

  return res.json({ analysis: simulatedReport, generatedByAI: false });
});

// Generate fresh custom sports article via AI Gemini based on user prompt Search topic!
app.post("/api/ai/custom-news", async (req, res) => {
  const { topic, language } = req.body;
  const isAr = language === "ar";

  if (!topic) {
    return res.status(400).json({ error: isAr ? "يرجى تحديد موضوع البحث" : "Topic is required" });
  }

  if (ai) {
    try {
      const prompt = isAr
        ? `اكتب مقالاً رياضياً مشوقاً وعريضاً كخبر عاجل باللغة العربية حول الموضوع الرياضي التالي: (${topic}). اكتب عنواناً جذاباً وملخصاً صغيراً ثم مقالاً من فقرتين قصيرة. لا تزد عن 130 كلمة واجعله في قالب حماسي ممتاز وشيق لصحيفة كرة قدم عالمية.`
        : `Write an exciting sports breaking news article about this topic: (${topic}). Provide an attractive headline, a brief one-line summary, and a text story of 2 short paragraphs. Limit to 130 words. Write in the voice of a leading international football news anchor.`;

      const response = await ai.models.generateContent({
        model: "gemini-3.5-flash",
        contents: prompt,
      });

      const responseText = response.text || "";
      
      // Parse or mock extract: Title, Summary, Body
      // Let's return as a new dynamic news item
      const item: NewsItem = {
        id: `news-ai-${Date.now()}`,
        titleEn: isAr ? `${topic} - Dynamic Spotlight` : `Breaking: Exclusive report on ${topic}`,
        titleAr: isAr ? `تقرير خاص ومباشر: ${topic}` : `تقرير خاص: ${topic} - تسليط بؤرة الضوء`,
        summaryEn: isAr ? "AI-Generated Sports Breakdown" : `Automated live commentary regarding details surrounding ${topic}.`,
        summaryAr: isAr ? "تحليل فوري تفصيلي تم سحبه تلقائياً عبر الذكاء الاصطناعي" : "خبر رياضي عاجل تم توليده آلياً بناءً على طلبك بالدقة الرياضية.",
        contentEn: isAr ? "AI analysis completed in Arabic" : responseText,
        contentAr: isAr ? responseText : "تم صياغة الخبر استجابة لعملية البحث.",
        category: "news",
        image: "🗞️",
        publishedAt: new Date().toISOString()
      };

      // Unshift item into local news database list to simulate real-time news updates
      news.unshift(item);
      return res.json({ success: true, newsItem: item });

    } catch (err: any) {
      console.error("Gemini failed during custom news generation:", err);
    }
  }

  // Simulated fallback news generator if Gemini key is not activated
  const simulatedNews: NewsItem = {
    id: `news-sim-${Date.now()}`,
    titleAr: `تغطية خاصة: آخر مستجدات البحث عن "${topic}"`,
    titleEn: `Exclusive Report: Latest developments on "${topic}"`,
    summaryAr: "تحليل تلقائي متكامل بناء على طلب البحث الفوري في الدوريات العالمية.",
    summaryEn: "Instant automatic breakdown based on requested search topic.",
    contentAr: `شهدت الساعات الأخيرة تحركات وتصريحات قوية بخصوص موضوع "${topic}" في أوساط كرة القدم العالمية. يتوقع نقاد الكرة أن يحمل هذا الملف مفاجآت مدوية تعيد رسم موازين القوى والتنافس بين عمالقة اللعبة مع تسليط الضوء على الآثار الفنية للموسم الجاري والصفقات المرتقبة.`,
    contentEn: `The soccer world has observed intense events in recent times regarding "${topic}". Football veterans expect major turns that could alter league balance, potentially triggering high-profile shifts in squad setups and future planning metrics.`,
    category: "news",
    image: "📊",
    publishedAt: new Date().toISOString()
  };

  news.unshift(simulatedNews);
  res.json({ success: true, newsItem: simulatedNews });
});

// ==========================================
// VITE AND MIDDLEWARE INTEGRATION
// ==========================================

async function startServer() {
  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Live Football Hub Server listening on http://0.0.0.0:${PORT}`);
  });
}

startServer();
