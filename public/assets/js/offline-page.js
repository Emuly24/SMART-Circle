/* Lists the lessons and notes saved on this device (from the index offline.js keeps). */
(function () {
    var list = document.getElementById('saved');
    var items = [];
    try { items = JSON.parse(localStorage.getItem('sc-offline-index') || '[]'); } catch (e) { items = []; }
    if (!list || items.length === 0) { return; }
    list.textContent = '';
    items.forEach(function (it) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = it.url;
        a.textContent = it.title;
        li.appendChild(a);
        list.appendChild(li);
    });
})();
