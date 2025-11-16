function escapeHtml(s) {
    return String(s).replace(/[&<>"]+/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
}

function decodeHtml(s) {
    var e = document.createElement("textarea");
    e.innerHTML = String(s);
    return e.value;
}

function labelClass(n) {
    n = String(n || "").toLowerCase();
    if (n === "bug") return "bg-red-600 text-white";
    if (n === "feature") return "bg-green-600 text-white";
    if (n === "docs") return "bg-purple-600 text-white";
    if (n === "improvement") return "bg-blue-600 text-white";
    if (n === "chore") return "bg-yellow-600 text-white";
    if (n === "backend") return "bg-blue-600 text-white";
    if (n === "front" || n === "frontend") return "bg-green-600 text-white";
    if (n === "admin") return "bg-purple-600 text-white";
    if (n === "test") return "bg-red-600 text-white";
    if (n === "seo") return "bg-purple-600 text-white";
    if (n === "design") return "bg-purple-600 text-white";
    if (n === "bdd") return "bg-yellow-600 text-white";
    if (n === "p1") return "bg-red-600 text-white";
    if (n === "p2") return "bg-yellow-600 text-white";
    if (n === "p3") return "bg-green-600 text-white";
    if (n.startsWith("temps")) return "bg-blue-600 text-white";
    return "bg-gray-700 text-gray-100";
}

function priorityClass(p) {
    p = String(p || "").toLowerCase();
    if (p === "high") return "bg-red-600 text-white";
    if (p === "low") return "bg-green-600 text-white";
    return "bg-yellow-600 text-white";
}

function labelText(n) {
    n = String(n || "").toLowerCase();
    if (n === "bug") return "Bug";
    if (n === "feature") return "Fonctionnalité";
    if (n === "docs") return "Documentation";
    if (n === "improvement") return "Amélioration";
    if (n === "chore") return "Maintenance";
    if (n === "backend") return "Backend";
    if (n === "front" || n === "frontend") return "Frontend";
    if (n === "admin") return "Admin";
    if (n === "test") return "Test";
    if (n === "seo") return "SEO";
    if (n === "design") return "Design";
    if (n === "bdd") return "BDD";
    if (n === "p1") return "P1";
    if (n === "p2") return "P2";
    if (n === "p3") return "P3";
    if (n.startsWith("temps")) return "Temps " + n.replace("temps", "").replace(":", "").trim();
    return n;
}

function priorityText(p) {
    p = String(p || "").toLowerCase();
    if (p === "high") return "Élevée";
    if (p === "low") return "Basse";
    return "Moyenne";
}

function updatePriorityPreview(p) {
    var el = document.getElementById("priority-preview");
    if (!el) return;
    var cls = priorityClass(p);
    var txt = priorityText(p);
    el.innerHTML = "";
    var span = document.createElement("span");
    span.className = "inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium ring-2 ring-white " + cls;
    var icon = document.createElement("i");
    icon.className = "fa-solid fa-check";
    span.appendChild(icon);
    span.appendChild(document.createTextNode(txt));
    el.appendChild(span);
}

function updateLabelsPreview() {
    var preview = document.getElementById("labels-preview");
    if (!preview) return;
    var names = new Set();
    Array.from(selected).forEach(function (n) {
        names.add(n);
    });
    preview.innerHTML = "";
    names.forEach(function (n) {
        var span = document.createElement("span");
        span.className =
            "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium " +
            labelClass(n) +
            " ring-2 ring-white";
        var icon = document.createElement("i");
        icon.className = "fa-solid fa-check";
        span.appendChild(icon);
        var text = document.createTextNode(n);
        span.appendChild(text);
        preview.appendChild(span);
    });
}

function parseDateString(str) {
    if (!str) return null;
    var s = String(str).trim();
    if (s.indexOf('T') < 0 && s.indexOf('Z') < 0) {
        s = s.replace(' ', 'T');
    }
    var d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
}

function formatFrenchDate(str) {
    var d = parseDateString(str);
    if (!d) return String(str || '');
    function pad(n){return (n<10?'0':'')+n;}
    return pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear()+' '+pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds());
}