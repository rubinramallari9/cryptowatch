/**
 * CryptoWatch — api_request.js
 * Wrapper i pastër për CoinGecko API.
 * Mund të importohet si modul ose të përdoret drejtpërdrejt.
 */

const CoinGecko = (() => {
    const BASE = 'https://api.coingecko.com/api/v3';

    async function get(endpoint, params = {}) {
        const url = new URL(BASE + endpoint);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        const res = await fetch(url.toString());
        if (!res.ok) throw new Error(`CoinGecko ${res.status}: ${res.statusText}`);
        return res.json();
    }

    return {
        /**
         * Merr listën e tregut.
         * @param {number} perPage - Sa monedha (default 10)
         */
        getMarkets(perPage = 10) {
            return get('/coins/markets', {
                vs_currency: 'usd',
                order: 'market_cap_desc',
                per_page: perPage,
                page: 1,
                sparkline: false,
                price_change_percentage: '24h',
            });
        },

        /**
         * Merr grafikun e çmimit (të dhëna historike).
         * @param {string} coinId  - p.sh. 'bitcoin'
         * @param {number} days    - numri i ditëve
         */
        getChart(coinId, days = 7) {
            return get(`/coins/${coinId}/market_chart`, {
                vs_currency: 'usd',
                days,
            });
        },

        /**
         * Merr detajet e një monedhe specifike.
         * @param {string} coinId
         */
        getCoinDetail(coinId) {
            return get(`/coins/${coinId}`, {
                localization: false,
                tickers: false,
                community_data: false,
                developer_data: false,
            });
        },
    };
})();
