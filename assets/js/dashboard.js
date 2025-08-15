(function(){
    document.addEventListener('DOMContentLoaded', function(){
        if (typeof go360Logs === 'undefined') { return; }
        const feed = document.querySelector('.dashboard__activity-feed');
        if (!feed) { return; }
        const btn = document.getElementById('logs-load-more');
        let page = 1;
        const perPage = 10;

        async function load(){
            const res = await fetch(`${go360Logs.endpoint}?page=${page}&per_page=${perPage}`, {
                headers:{'X-WP-Nonce': go360Logs.nonce}
            });
            if(!res.ok){ return; }
            const data = await res.json();
            if(!Array.isArray(data) || data.length === 0){
                if(btn) btn.style.display='none';
                return;
            }
            data.forEach(log => {
                const item = document.createElement('div');
                item.className = 'dashboard__activity-item';
                const time = document.createElement('div');
                time.className = 'dashboard__activity-time';
                time.textContent = new Date(log.created_at).toLocaleString();
                const content = document.createElement('div');
                content.className = 'dashboard__activity-content';
                content.textContent = log.details || log.event_type;
                item.appendChild(time);
                item.appendChild(content);
                feed.appendChild(item);
            });
            if(btn) btn.style.display='block';
        }

        if(btn){
            btn.addEventListener('click', function(){
                page++;
                load();
            });
        }
        load();
    });
})();
