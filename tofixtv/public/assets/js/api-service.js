/*!
 * ALOKA Live — client API service.
 *
 * The frontend talks ONLY to first-party /api/*.php proxy endpoints. The
 * external scores API and its required app headers stay server-side; no
 * external URL ever appears here. All methods return the parsed JSON envelope
 * { ok, stale, data, ... } and throw on a transport error so callers can show
 * a friendly message and/or fall back to cached content.
 */
(function (w) {
  'use strict';

  var BASE = '/api';
  var LANG = (document.documentElement.getAttribute('lang') || 'ar').indexOf('en') === 0 ? 'en' : 'ar';

  function ymd(d) {
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  function get(path) {
    var url = BASE + path + (path.indexOf('?') === -1 ? '?' : '&') + 'lang=' + LANG;
    return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok && r.status >= 500) throw new Error('upstream ' + r.status);
        return r.json();
      });
  }

  var matchesService = {
    /** Matches for a given YYYY-MM-DD (defaults to today). */
    getMatches: function (date) {
      if (date instanceof Date) date = ymd(date);
      return get('/matches.php' + (date ? '?date=' + encodeURIComponent(date) : ''));
    },
    /** Matches currently in play. */
    getLiveMatches: function () { return get('/live.php'); },
    /** Yesterday's matches. */
    getYesterdayMatches: function () { var d = new Date(); d.setDate(d.getDate() - 1); return this.getMatches(ymd(d)); },
    /** Tomorrow's matches. */
    getTomorrowMatches: function () { var d = new Date(); d.setDate(d.getDate() + 1); return this.getMatches(ymd(d)); }
  };

  var newsService = {
    getLatest: function (page) { return get('/news.php?page=' + (page || 1)); },
    getArticle: function (id) { return get('/news.php?id=' + encodeURIComponent(id)); }
  };

  var playerService = { get: function (id, slug) { return get('/player.php?id=' + encodeURIComponent(id) + (slug ? '&slug=' + encodeURIComponent(slug) : '')); } };
  var teamService = { get: function (id) { return get('/team.php?id=' + encodeURIComponent(id)); } };
  var standingsService = { get: function (leagueId) { return get('/standings.php?league=' + encodeURIComponent(leagueId)); } };

  w.matchesService = matchesService;
  w.newsService = newsService;
  w.playerService = playerService;
  w.teamService = teamService;
  w.standingsService = standingsService;
  w.QApi = { matches: matchesService, news: newsService, player: playerService, team: teamService, standings: standingsService };
})(window);
