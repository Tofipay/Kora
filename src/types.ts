export interface MatchEvent {
  minute: number;
  type: "goal" | "card_yellow" | "card_red" | "substitution";
  team: "home" | "away";
  playerEn: string;
  playerAr: string;
  detailEn: string;
  detailAr: string;
}

export interface Match {
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
  date: string;
  events: MatchEvent[];
}

export interface Standing {
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

export interface League {
  id: string;
  nameEn: string;
  nameAr: string;
  countryEn: string;
  countryAr: string;
  logo: string;
  standings: Standing[];
}

export interface NewsItem {
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

export interface LiveAlert {
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
