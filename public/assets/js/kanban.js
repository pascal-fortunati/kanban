// Gestion de la déplacement de tâches entre colonnes
let draggedId = null;
var selected = new Set();
function openModal(m){
    if(!m) return;
    m.classList.remove('hidden','closing');
    m.classList.add('flex');
    requestAnimationFrame(function(){ m.classList.add('open'); });
}
function closeModal(m){
    if(!m) return;
    m.classList.remove('open');
    m.classList.add('closing');
    var content = m.querySelector('.modal-content');
    var overlay = m.querySelector('.modal-overlay');
    var done=false;
    function finish(){
        if(done) return; done=true;
        m.classList.add('hidden');
        m.classList.remove('flex','closing');
        if(content) content.removeEventListener('transitionend', finish);
        if(overlay) overlay.removeEventListener('transitionend', finish);
    }
    if(content) content.addEventListener('transitionend', finish);
    if(overlay) overlay.addEventListener('transitionend', finish);
    setTimeout(finish,250);
}
 

// Gestion de l'événement de début de déplacement d'une tâche
function handleDragStart(e) {
    draggedId = e.target.dataset.id;
}

// Gestion de la déplacement de tâches entre colonnes
async function handleDrop(e) {
    const status = e.currentTarget.dataset.status;
    if (!draggedId) return;
    const card = document.querySelector('[data-id="' + draggedId + '"]');
    const col = document.getElementById("col-" + status);
    const prevCol = card ? card.parentElement : null;
    if (card && col) col.prepend(card);
    try {
        const res = await fetch((window.APP_BASE || "") + "/kanban/move", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: parseInt(draggedId, 10), status, csrf_token: (window.CSRF_TOKEN || '') }),
        });
        const data = await res.json();
        if (!data.success) throw new Error("error");
    } catch (err) {
        if (card && prevCol) prevCol.prepend(card);
        if (window.toastError) toastError("Déplacement de tâche impossible");
    }
    draggedId = null;
}

// Ouverture de la fenêtre de création de tâche
function openCreateTask() {
    const m = document.getElementById("create-modal");
    if (m) {
        try {
            updatePriorityPreview("medium");
        } catch (e) {}
        openModal(m);
        return;
    }
    var base = window.APP_BASE || "";
    var ret = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = base + "/kanban?new_task=1&return=" + ret;
}

// Fermeture de la fenêtre de création de tâche
function closeCreateTask() {
    const m = document.getElementById("create-modal");
    if (m) {
        closeModal(m);
        try {
            var form = document.getElementById("create-task-form");
            if (form) {
                var idInput = form.querySelector('input[name="id"]');
                if (idInput) idInput.value = "";
            }
            var titleEl = document.getElementById("create-modal-title");
            if (titleEl) titleEl.textContent = "Créer une tâche";
            var submitEl = document.getElementById("create-submit");
            if (submitEl) submitEl.textContent = "Créer";
            var titleInput = form ? form.querySelector('input[name="title"]') : null;
            if (titleInput) titleInput.value = "";
            var descInput = form ? form.querySelector('textarea[name="description"]') : null;
            if (descInput) {
                descInput.value = "";
                var pv = document.getElementById("create-code-preview");
                if (pv && window.hljs) {
                    pv.textContent = "";
                    try {
                        hljs.highlightElement(pv);
                    } catch (e) {}
                }
            }
            var prLow = document.getElementById("prio-low");
            var prMed = document.getElementById("prio-medium");
            var prHigh = document.getElementById("prio-high");
            if (prLow && prMed && prHigh) {
                prLow.checked = false;
                prHigh.checked = false;
                prMed.checked = true;
                var labels = document.getElementById("priority-group");
                if (labels) {
                    Array.from(labels.querySelectorAll("label")).forEach(function (l) {
                        l.classList.remove("ring-2", "ring-white");
                    });
                    var lab = document.querySelector('label[for="prio-medium"]');
                    if (lab) lab.classList.add("ring-2", "ring-white");
                }
            }
            selected.clear();
            var quick = document.getElementById("quick-labels");
            if (quick)
                Array.from(quick.querySelectorAll("button[data-name]")).forEach(function (b) {
                    b.classList.remove("ring-2", "ring-white");
                });
            var labelsPreview = document.getElementById("labels-preview");
            if (labelsPreview) labelsPreview.innerHTML = "";
            var prPreview = document.getElementById("priority-preview");
            if (prPreview) prPreview.innerHTML = "";
            var errorEl = document.getElementById("create-error");
            if (errorEl) errorEl.textContent = "";
        } catch (e) {}
    }
    try {
        var params = new URLSearchParams(window.location.search);
        var ret = params.get("return");
        if (ret) {
            var base = window.APP_BASE || "";
            if (/^https?:\/\//i.test(ret) || (base && ret.indexOf(base) === 0)) {
                window.location.href = ret;
            } else {
                window.location.href = base + ret;
            }
        }
    } catch (e) {}
}

// Ouverture de la fenêtre de visualisation de tâche
function openTaskModal(el) {
    const m = document.getElementById("view-modal");
    if (!m || !el) return;
    const titleEl = document.getElementById("view-title");
    const prEl = document.getElementById("view-priority");
    const labelsEl = document.getElementById("view-labels");
    const codeEl = document.getElementById("view-code");
    const title = el.dataset.title || "";
    const pr = String(el.dataset.priority || "medium").toLowerCase();
    const prCls = pr === "high" ? "bg-red-600" : pr === "low" ? "bg-green-600" : "bg-yellow-600";
    const prTxt = priorityText(pr);
    const b64 = el.dataset.labelsB64 || "";
    let labs = [];
    try {
        if (b64) labs = JSON.parse(atob(b64));
    } catch (e) {}
    if (titleEl) titleEl.textContent = title;
    if (prEl) {
        prEl.className = "text-xs px-2 py-1 rounded " + prCls + " text-white";
        prEl.textContent = prTxt;
    }
    if (labelsEl) {
        labelsEl.innerHTML = "";
        if (Array.isArray(labs)) {
            labs.forEach((l) => {
                var s = document.createElement("span");
                s.className =
                    "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium " +
                    labelClass(l.name || "");
                s.textContent = labelText(l.name || "");
                labelsEl.appendChild(s);
            });
        }
    }
    if (codeEl) {
        codeEl.textContent = decodeHtml(el.dataset.description || "");
        if (window.hljs) {
            try {
                codeEl.removeAttribute('data-highlighted');
                hljs.highlightElement(codeEl);
            } catch (e) {}
        }
    }
    window.__currentTask = {
        id: parseInt(el.dataset.id || "0", 10),
        title: title,
        priority: pr,
        description: String(el.dataset.description || ""),
        labels: Array.isArray(labs) ? labs : [],
    };
    openModal(m);
}

// Fermeture de la fenêtre de visualisation de tâche
function closeTaskModal() {
    const m = document.getElementById("view-modal");
    if (m) { closeModal(m); }
    try {
        window.__currentTask = null;
    } catch (e) {}
}

// Édition de la tâche: disponible globalement et indépendante de GitHub
window.editCurrentTask = async function () {
    var t = window.__currentTask || null;
    if (!t) return;
    var m = document.getElementById("create-modal");
    var form = document.getElementById("create-task-form");
    if (!m || !form) return;
    try { closeTaskModal(); } catch (e) {}
    var payload = t;
    try {
        var res = await fetch((window.APP_BASE || "") + "/kanban/task/" + encodeURIComponent(String(t.id || "")));
        var d = await res.json();
        if (d && d.success && d.task) { payload = d.task; }
    } catch (e) {}
    var idInput = form.querySelector('input[name="id"]');
    var titleInput = form.querySelector('input[name="title"]');
    var descInput = form.querySelector('textarea[name="description"]');
    var prLow = document.getElementById("prio-low");
    var prMed = document.getElementById("prio-medium");
    var prHigh = document.getElementById("prio-high");
    if (idInput) idInput.value = String(payload.id || "");
    if (titleInput) titleInput.value = String(payload.title || "");
    if (descInput) {
        descInput.value = String(payload.description || "");
        var pv = document.getElementById("create-code-preview");
        if (pv) {
            pv.textContent = decodeHtml(descInput.value || "");
            if (window.hljs) {
                try { pv.removeAttribute('data-highlighted'); hljs.highlightElement(pv); } catch (e) {}
            }
        }
    }
    if (prLow && prMed && prHigh) {
        var pr = String(payload.priority || "medium").toLowerCase();
        prLow.checked = pr === "low";
        prMed.checked = pr === "medium";
        prHigh.checked = pr === "high";
        var grp = document.getElementById("priority-group");
        if (grp) {
            Array.from(grp.querySelectorAll("label")).forEach(function (l) { l.classList.remove("ring-2", "ring-white"); });
            var lab = document.querySelector('label[for="' + (pr === "low" ? "prio-low" : pr === "high" ? "prio-high" : "prio-medium") + '"]');
            if (lab) lab.classList.add("ring-2", "ring-white");
        }
        try { updatePriorityPreview(pr); } catch (e) {}
    }
    selected.clear();
    var quick = document.getElementById("quick-labels");
    if (quick) Array.from(quick.querySelectorAll("button[data-name]")).forEach(function (b) { b.classList.remove("ring-2", "ring-white"); });
    try {
        (payload.labels || []).forEach(function (l) {
            var n = String((l && l.name) || "").trim();
            if (n) { selected.add(n); var btnEl = document.querySelector('#quick-labels button[data-name="' + n + '"]'); if (btnEl) btnEl.classList.add("ring-2", "ring-white"); }
        });
    } catch (e) {}
    updateLabelsPreview();
    var titleEl = document.getElementById("create-modal-title");
    if (titleEl) titleEl.textContent = "Éditer une tâche";
    var submitEl = document.getElementById("create-submit");
    if (submitEl) submitEl.textContent = "Mettre à jour";
    openModal(m);
};

// Gestion de la création de tâches
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("create-task-form");
    const descInputLive = form ? form.querySelector('textarea[name="description"]') : null;
    if (descInputLive) {
        descInputLive.addEventListener("input", function () {
            var pv = document.getElementById("create-code-preview");
            if (pv) {
                pv.textContent = decodeHtml(descInputLive.value || "");
                if (window.hljs) {
                    try {
                        pv.removeAttribute('data-highlighted');
                        hljs.highlightElement(pv);
                    } catch (e) {}
                }
            }
        });
    }
    const quick = document.getElementById("quick-labels");
    const overlay = document.querySelector("#create-modal .absolute");
    if (overlay) overlay.addEventListener("click", closeCreateTask);
    const overlayView = document.querySelector("#view-modal .absolute");
    if (overlayView) overlayView.addEventListener("click", closeTaskModal);
    const overlayBroadcast = document.querySelector("#broadcast-modal .absolute");
    if (overlayBroadcast) overlayBroadcast.addEventListener("click", closeBroadcastModal);
    const overlayCommits = document.querySelector("#commits-modal .absolute");
    if (overlayCommits) overlayCommits.addEventListener("click", closeCommitsModal);
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closeCreateTask();
            closeTaskModal();
            closeBroadcastModal();
            closeCommitsModal();
        }
    });
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get("new_task") === "1") {
            openCreateTask();
        }
    } catch (e) {}
    try {
        Array.from(document.querySelectorAll('a[href$="/auth/logout"]')).forEach(function (a) {
            a.addEventListener("click", function (ev) {
                ev.preventDefault();
                var href = a.getAttribute("href") || (window.APP_BASE || "") + "/auth/logout";
                try {
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: "success",
                            title: "Déconnexion réussie",
                            timer: 900,
                            showConfirmButton: false,
                            background: "#1f2937",
                            color: "#e5e7eb",
                        }).then(function () {
                            window.location.href = href;
                        });
                    } else {
                        window.location.href = href;
                    }
                } catch (e) {
                    window.location.href = href;
                }
            });
        });
    } catch (e) {}
    const prioGroup = document.getElementById("priority-group");
    if (prioGroup) {
        prioGroup.addEventListener("click", (e) => {
            const label = e.target.closest("label[for]");
            if (!label) return;
            const id = label.getAttribute("for");
            const input = document.getElementById(id);
            if (!input) return;
            input.checked = true;
            Array.from(prioGroup.querySelectorAll("label")).forEach((l) => l.classList.remove("ring-2", "ring-white"));
            label.classList.add("ring-2", "ring-white");
            try {
                var pr = (input.value || "medium").toLowerCase();
                updatePriorityPreview(pr);
            } catch (e) {}
        });
    }
    if (quick) {
        quick.addEventListener("click", (e) => {
            const btn = e.target.closest("button[data-name]");
            if (!btn) return;
            const name = btn.dataset.name;
            const key = name;
            if (selected.has(key)) {
                selected.delete(key);
                btn.classList.remove("ring-2", "ring-white");
            } else {
                selected.add(key);
                btn.classList.add("ring-2", "ring-white");
            }
            updateLabelsPreview();
        });
    }
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            fd.set("csrf_token", (window.CSRF_TOKEN || ''));
            const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector("button");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add("opacity-75");
            }
            const chips = Array.from(selected);
            const parts = [];
            if (chips.length) parts.push(chips.join(","));
            fd.set("labels", parts.join(","));

            var isEdit = !!String(fd.get("id") || "").trim();
            var endpoint = (window.APP_BASE || "") + (isEdit ? "/kanban/update" : "/kanban/create");
            try {
                const res = await fetch(endpoint, {
                    method: "POST",
                    body: fd,
                });
                const data = await res.json();

                if (data.success) {
                    const t = data.task;
                    if (isEdit) {
                        var card = document.querySelector('[data-id="' + String(t.id) + '"]');
                        if (card) {
                            var prioCls =
                                t.priority === "high"
                                    ? "bg-red-600"
                                    : t.priority === "low"
                                      ? "bg-green-600"
                                      : "bg-yellow-600";
                            var titleNode = card.querySelector(".font-semibold");
                            if (titleNode) titleNode.textContent = String(t.title || "");
                            var badge = card.querySelector(".text-xs");
                            if (badge) {
                                badge.className = "text-xs px-2 py-1 rounded " + prioCls + " text-white";
                                badge.textContent = priorityText(t.priority);
                            }
                            card.dataset.title = String(t.title || "");
                            card.dataset.priority = String(t.priority || "");
                            card.dataset.description = String(t.description || "");
                            try {
                                card.dataset.labelsB64 = btoa(JSON.stringify(Array.isArray(t.labels) ? t.labels : []));
                            } catch (e) {
                                card.dataset.labelsB64 = btoa("[]");
                            }
                        }
                        closeCreateTask();
                        closeTaskModal();
                        const errorEl = document.getElementById("create-error");
                        if (errorEl) errorEl.textContent = "";
                        selected.clear();
                        if (quick)
                            Array.from(quick.querySelectorAll("button[data-name]")).forEach((b) =>
                                b.classList.remove("ring-2", "ring-white")
                            );
                        updateLabelsPreview();
                        if (window.toastSuccess) toastSuccess("Tâche mise à jour");
                    } else {
                        const card = document.createElement("div");
                        card.className =
                            "bg-gray-800 border border-gray-700 hover:border-blue-500 rounded p-3 space-y-2 cursor-pointer transition-colors shadow-sm hover:shadow-md";
                        card.setAttribute("draggable", "true");
                        card.setAttribute("data-id", String(t.id));
                        card.addEventListener("dragstart", handleDragStart);
                        const prioCls =
                            t.priority === "high"
                                ? "bg-red-600"
                                : t.priority === "low"
                                  ? "bg-green-600"
                                  : "bg-yellow-600";
                        card.innerHTML =
                            '<div class="flex items-center justify-between"><div class="font-semibold">' +
                            escapeHtml(t.title) +
                            '</div><span class="text-xs px-2 py-1 rounded ' +
                            prioCls +
                            ' text-white">' +
                            priorityText(t.priority) +
                            "</span></div>";
                        card.dataset.title = String(t.title || "");
                        card.dataset.priority = String(t.priority || "");
                        card.dataset.description = String(t.description || "");
                        try {
                            card.dataset.labelsB64 = btoa(JSON.stringify(Array.isArray(t.labels) ? t.labels : []));
                        } catch (e) {
                            card.dataset.labelsB64 = btoa("[]");
                        }
                        card.addEventListener("click", () => openTaskModal(card));
                        const col = document.getElementById("col-todo");
                        if (col) col.prepend(card);
                        form.reset();
                        closeCreateTask();
                        const errorEl = document.getElementById("create-error");
                        if (errorEl) errorEl.textContent = "";
                        selected.clear();
                        if (quick)
                            Array.from(quick.querySelectorAll("button[data-name]")).forEach((b) =>
                                b.classList.remove("ring-2", "ring-white")
                            );
                        updateLabelsPreview();
                        if (window.toastSuccess) toastSuccess("Tâche créée");
                    }
                } else {
                    const errorEl = document.getElementById("create-error");
                    if (errorEl) errorEl.textContent = data.error || "Erreur";
                    if (window.toastError) toastError(data.error || "Erreur");
                }
            } catch (err) {
                const errorEl = document.getElementById("create-error");
                if (errorEl) errorEl.textContent = "Erreur réseau";
                if (window.toastError) toastError("Erreur réseau");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove("opacity-75");
                }
            }
        });
    }

    var loginForm = document.getElementById("login-form");
    if (loginForm) {
        var loginEmail = document.getElementById("login-email");
        var loginPw = document.getElementById("login-password");
        var loginErr = document.getElementById("login-error");
        var loginBtn = loginForm.querySelector("button");
        var loginTouched = false;
        function validateLogin(forceShow) {
            var e = loginEmail ? String(loginEmail.value || "") : "";
            var p = loginPw ? String(loginPw.value || "") : "";
            var msgs = [];
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) msgs.push("Email invalide");
            if (p === "") msgs.push("Mot de passe requis");
            if (loginErr) loginErr.textContent = (loginTouched || !!forceShow) ? msgs.join(" • ") : "";
            var ok = msgs.length === 0;
            if (loginBtn) loginBtn.disabled = !ok;
            return ok;
        }
        loginForm.addEventListener("input", function(){ loginTouched = true; validateLogin(true); });
        validateLogin(false);
        loginForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            var ok = validateLogin(true);
            if (!ok) { if (window.alertError) alertError("Connexion", "Veuillez vérifier les champs"); return; }
            var fd = new FormData(loginForm);
            fd.set("csrf_token", (window.CSRF_TOKEN || ''));
            var btn = loginForm.querySelector("button");
            var err = document.getElementById("login-error");
            if (err) err.textContent = "";
            if (btn) {
                btn.disabled = true;
                btn.classList.add("opacity-75");
            }
            var wasLoading = false;
            try {
                if (window.Swal) {
                    wasLoading = true;
                    window.Swal.fire({
                        title: "Connexion…",
                        allowOutsideClick: false,
                        didOpen: function () {
                            try {
                                window.Swal.showLoading();
                            } catch (e) {}
                        },
                        background: "#1f2937",
                        color: "#e5e7eb",
                    });
                }
            } catch (e) {}
            try {
                var r = await fetch((window.APP_BASE || "") + "/auth/doLogin", { method: "POST", body: fd, credentials: "same-origin" });
                var d = await r.json();
                try {
                    if (wasLoading && window.Swal) window.Swal.close();
                } catch (e) {}
                if (d.success) {
                    var dest = d.redirect || (window.APP_BASE || "") + "/kanban";
                    var go = function () {
                        window.location.href = dest;
                    };
                    try {
                        if (window.Swal) {
                            window.Swal.fire({
                                icon: "success",
                                title: "Connexion réussie",
                                timer: 900,
                                showConfirmButton: false,
                                background: "#1f2937",
                                color: "#e5e7eb",
                            }).then(go);
                        } else {
                            go();
                        }
                    } catch (e) {
                        go();
                    }
                } else {
                    var em = d.error || "Email ou mot de passe incorrect";
                    if (err) err.textContent = em;
                    if (window.alertError) alertError("Connexion", em);
                }
            } catch (ex) {
                try {
                    if (wasLoading && window.Swal) window.Swal.close();
                } catch (e) {}
                if (err) err.textContent = "Erreur réseau";
                if (window.alertError) alertError("Connexion", "Erreur réseau");
            }
            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-75");
            }
        });
    }

    var registerForm = document.getElementById("register-form");
    if (registerForm) {
        var regPw = document.getElementById("reg-password");
        var regCf = document.getElementById("reg-confirm");
        var regHint = document.getElementById("register-hint");
        var regBtn = registerForm.querySelector("button");
        function validateRegister() {
            var p = regPw ? String(regPw.value || "") : "";
            var c = regCf ? String(regCf.value || "") : "";
            var msgs = [];
            if (p.length < 8) msgs.push("Au moins 8 caractères");
            if (!/[a-z]/.test(p) || !/[A-Z]/.test(p) || !/[0-9]/.test(p)) msgs.push("Inclure majuscule, minuscule et chiffre");
            if (c !== "" && p !== c) msgs.push("Confirmation différente");
            if (regHint) regHint.textContent = msgs.join(" • ");
            var ok = msgs.length === 0;
            if (regBtn) regBtn.disabled = !ok;
            return ok;
        }
        registerForm.addEventListener("input", function(){ validateRegister(); });
        validateRegister();
        registerForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            var ok = validateRegister();
            if (!ok) { var err = document.getElementById("register-error"); if (err) err.textContent = "Veuillez vérifier les champs"; if (window.alertError) alertError("Inscription", "Veuillez vérifier les champs"); return; }
            var fd = new FormData(registerForm);
            fd.set("csrf_token", (window.CSRF_TOKEN || ''));
            var btn = registerForm.querySelector("button");
            var err = document.getElementById("register-error");
            if (err) err.textContent = "";
            if (btn) {
                btn.disabled = true;
                btn.classList.add("opacity-75");
            }
            var wasLoading = false;
            try {
                if (window.Swal) {
                    wasLoading = true;
                    window.Swal.fire({
                        title: "Inscription…",
                        allowOutsideClick: false,
                        didOpen: function () {
                            try {
                                window.Swal.showLoading();
                            } catch (e) {}
                        },
                        background: "#1f2937",
                        color: "#e5e7eb",
                    });
                }
            } catch (e) {}
            try {
                var r = await fetch((window.APP_BASE || "") + "/auth/doRegister", { method: "POST", body: fd, credentials: "same-origin" });
                var d = await r.json();
                try {
                    if (wasLoading && window.Swal) window.Swal.close();
                } catch (e) {}
                if (d.success) {
                    var dest = d.redirect || (window.APP_BASE || "") + "/auth/login";
                    var go = function () {
                        window.location.href = dest;
                    };
                    try {
                        if (window.Swal) {
                            window.Swal.fire({
                                icon: "success",
                                title: "Compte créé",
                                timer: 900,
                                showConfirmButton: false,
                                background: "#1f2937",
                                color: "#e5e7eb",
                            }).then(go);
                        } else {
                            go();
                        }
                    } catch (e) {
                        go();
                    }
                } else {
                    var em = d.error || "Veuillez vérifier les champs";
                    if (err) err.textContent = em;
                    if (window.alertError) alertError("Inscription", em);
                }
            } catch (ex) {
                try {
                    if (wasLoading && window.Swal) window.Swal.close();
                } catch (e) {}
                if (err) err.textContent = "Erreur réseau";
                if (window.alertError) alertError("Inscription", "Erreur réseau");
            }
            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-75");
            }
        });
    }
});

// Kanban et Dashboard
(function () {
    var repoOpenBtn = document.getElementById("open-create-repo");
    var repoModal = document.getElementById("create-repo-modal");
    var repoForm = document.getElementById("create-repo-form");
    var repoCreateStatus = document.getElementById("create-repo-status");
    var reposSyncBtn = document.getElementById("sync-repos");
    var sortDateBtn = document.getElementById("sort-date");
    var sortSizeBtn = document.getElementById("sort-size");
    var sortStarsBtn = document.getElementById("sort-stars");
    var repoList = document.querySelector(".space-y-2");
    var reposStatus = document.getElementById("repos-status");
    var broadcastForm = document.getElementById("broadcast-form");
    var broadcastStatus = document.getElementById("broadcast-status");
    var redeployBtn = document.getElementById("btn-redeploy-missed");
    if (redeployBtn) {
        var role = String(window.USER_ROLE || "");
        if (role === "student") {
            fetch((window.APP_BASE || "") + "/dashboard/getMissedBroadcasts")
                .then(function (r) {
                    if (!r.ok) throw new Error("x");
                    return r.json();
                })
                .then(function (d) {
                    var missed = d && d.missed ? d.missed : [];
                    var eligible = !!(d && d.eligible);
                    if (eligible && missed.length > 0) {
                        var latest = missed[0];
                        for (var i = 1; i < missed.length; i++) {
                            var a = new Date(String(missed[i].created_at || ""));
                            var b = new Date(String(latest.created_at || ""));
                            if (a > b) latest = missed[i];
                        }
                        window.__missedBroadcastLatest = latest;
                        redeployBtn.classList.remove("hidden");
                        if (window.toastInfo) {
                            var nm = String(latest.name || "Diffusion");
                            toastInfo("Tâches manquées: " + nm);
                        }
                    }
                })
                .catch(function () {});
            redeployBtn.addEventListener("click", async function () {
                var info = window.__missedBroadcastLatest || null;
                if (!info) {
                    if (window.toastError) toastError("Aucun déploiement de tâches manqué");
                    return;
                }
                var icon = redeployBtn.querySelector("i");
                var orig = icon ? icon.className : "";
                if (icon) {
                    icon.className = "fa-solid fa-spinner mr-2 fa-spin";
                }
                redeployBtn.disabled = true;
                redeployBtn.classList.add("opacity-75");
                try {
                    var fd = new URLSearchParams();
                    fd.append("broadcast_id", String(info.id || ""));
                    fd.append("owner_id", String(info.owner_id || ""));
                    var r = await fetch((window.APP_BASE || "") + "/dashboard/redeploySelfFromBroadcast", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: fd.toString() + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
                    });
                    var d = await r.json();
                    if (d && d.success) {
                        if (window.toastSuccess)
                            toastSuccess("Redéploiement effectué: " + (d.created || 0) + " tâche(s)");
                        redeployBtn.classList.add("hidden");
                        window.__missedBroadcastLatest = null;
                    } else {
                        if (window.toastError) toastError(String((d && d.error) || "Erreur"));
                    }
                } catch (e) {
                    if (window.toastError) toastError("Erreur réseau");
                }
                if (icon) {
                    icon.className = orig;
                }
                redeployBtn.disabled = false;
                redeployBtn.classList.remove("opacity-75");
            });
        }
    }
    var commitsAbort = null;
    var clearBtn = document.getElementById("clear-tasks-btn");
    var clearStatus = document.getElementById("clear-status");
    var openBroadcast = document.getElementById("open-broadcast");
    var broadcastModal = document.getElementById("broadcast-modal");
    var commitsModal = document.getElementById("commits-modal");
    var commitsTitle = document.getElementById("commits-title");
    var commitsSelect = document.getElementById("commits-repo-select");
    var commitsList = document.getElementById("commits-list");

    if (repoOpenBtn) {
        repoOpenBtn.addEventListener("click", function () {
            if (repoModal) { openModal(repoModal); }
        });
    }
    window.closeCreateRepoModal = function () {
        if (repoModal) { closeModal(repoModal); }
    };
    var overlayCreateRepo = document.querySelector("#create-repo-modal .absolute");
    if (overlayCreateRepo) overlayCreateRepo.addEventListener("click", closeCreateRepoModal);
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") { closeCreateRepoModal(); } });

    if (repoForm) {
        repoForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            if (repoCreateStatus) repoCreateStatus.textContent = "";
            var fd = new FormData(repoForm);
            fd.set("csrf_token", (window.CSRF_TOKEN || ''));
            var btn = repoForm.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.classList.add("opacity-75");
            }
            try {
                var res = await fetch((window.APP_BASE || "") + "/github/createRepository", {
                    method: "POST",
                    body: fd,
                });
                var data = await res.json();
                if (data.success) {
                    if (repoCreateStatus) {
                        repoCreateStatus.className = "text-green-400";
                        repoCreateStatus.textContent = "Repository créé";
                    }
                    if (window.Swal) {
                        window.Swal.fire({ icon: "success", title: "Repository créé", showConfirmButton: false, timer: 1200, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#98c379" }).then(function(){ location.reload(); });
                    } else {
                        location.reload();
                    }
                } else {
                    if (repoCreateStatus) {
                        repoCreateStatus.className = "text-red-400";
                        var msg = data.error || "Erreur";
                        if (msg === "not_connected") {
                            msg = "Connectez votre compte GitHub pour créer un repository.";
                        }
                        repoCreateStatus.textContent = msg;
                    }
                    if (window.toastError) toastError(msg);
                }
            } catch (err) {
                if (repoCreateStatus) {
                    repoCreateStatus.className = "text-red-400";
                    repoCreateStatus.textContent = "Erreur réseau";
                }
                if (window.toastError) toastError("Erreur réseau");
            }
            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-75");
            }
        });
    }

    window.deleteRepo = async function (btn) {
        var id = btn.getAttribute("data-id");
        var ok = true;
        try {
            ok = await window.confirmAsk({ title: "Supprimer ce repository ?", icon: "warning" });
        } catch (e) {
            ok = false;
        }
        if (!ok) return;
        var r = await fetch((window.APP_BASE || "") + "/github/deleteRepository", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + encodeURIComponent(id) + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
        });
        var d = await r.json();
        if (d.success) {
            if (window.Swal) {
                window.Swal.fire({ icon: "success", title: "Repository supprimé", showConfirmButton: false, timer: 1200, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#98c379" }).then(function(){ location.reload(); });
            } else {
                location.reload();
            }
        } else {
            if (d.error === "insufficient_scope") {
                var proceed = true;
                try {
                    proceed = await window.confirmAsk({
                        title: "Autoriser delete_repo",
                        text: "Vous devez revalider l’autorisation GitHub pour supprimer des repositories.",
                        icon: "info",
                        confirmText: "Autoriser",
                        cancelText: "Annuler",
                    });
                } catch (e) {
                    proceed = false;
                }
                if (proceed) {
                    window.location.href = (window.APP_BASE || "") + "/github/authenticate";
                }
                return;
            }
            var msg = d.error || "Erreur";
            if (msg === "not_connected") {
                msg = "Connectez votre compte GitHub pour supprimer le repository.";
            } else if (msg === "api_failed") {
                msg = "Permissions GitHub insuffisantes (delete_repo) ou erreur API.";
            }
            if (window.alertError) {
                alertError("Erreur", msg);
            }
        }
    };

    window.setActiveRepo = async function (btn) {
        var id = btn.getAttribute("data-id");
        var r = await fetch((window.APP_BASE || "") + "/profile/setActiveRepo", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + encodeURIComponent(id) + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
        });
        var d = await r.json();
        if (d.success) {
            if (window.Swal) {
                window.Swal.fire({ icon: "success", title: "Repository activé", showConfirmButton: false, timer: 1000, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#98c379" }).then(function(){ location.reload(); });
            } else {
                location.reload();
            }
        } else {
            var msg = d.error || "Erreur";
            if (window.alertError) {
                alertError("Erreur", msg);
            }
        }
    };

    window.deactivateRepo = async function (btn) {
        var r = await fetch((window.APP_BASE || "") + "/profile/deactivateActiveRepo", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || '') });
        var d = await r.json();
        if (d.success) {
            if (window.Swal) {
                window.Swal.fire({ icon: "success", title: "Repository désactivé", showConfirmButton: false, timer: 1000, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#98c379" }).then(function(){ location.reload(); });
            } else {
                location.reload();
            }
        } else {
            var msg = d.error || "Erreur";
            if (window.alertError) {
                alertError("Erreur", msg);
            }
        }
    };
    window.openProfileInstructions = function () {
        var connected = window.HAS_GITHUB || false;
        var statusBadge = connected
            ? '<span class="text-xs px-3 py-1 rounded-full" style="background-color: rgba(152,195,121,0.15); color:#98c379; border:1px solid rgba(152,195,121,0.35)">✓ GitHub connecté</span>'
            : '<span class="text-xs px-3 py-1 rounded-full" style="background-color: rgba(171,178,191,0.12); color:#abb2bf; border:1px solid rgba(171,178,191,0.25)">GitHub non connecté</span>';
        var html = `
      <div class="text-left space-y-4">
        <div class="flex items-center justify-between pb-3" style="border-bottom:1px solid #3b4048">
          <h3 class="text-lg font-semibold" style="color:#abb2bf">Guide rapide</h3>
          ${statusBadge}
        </div>
        <div class="space-y-3 text-sm" style="color:#abb2bf">
          <div class="p-3 rounded-lg" style="background-color: rgba(40,44,52,0.65); border:1px solid #3b4048">
            <p class="font-medium mb-1" style="color:#abb2bf">1. Connecter GitHub</p>
            <p style="color:#abb2bf">Utilisez le bouton "GitHub" dans le menu principal</p>
          </div>
          <div class="p-3 rounded-lg" style="background-color: rgba(40,44,52,0.65); border:1px solid #3b4048">
            <p class="font-medium mb-1" style="color:#abb2bf">2. Créer/Synchroniser</p>
            <p style="color:#abb2bf">Créez un nouveau repo ou synchronisez-en un existant</p>
          </div>
          <div class="p-3 rounded-lg" style="background-color: rgba(40,44,52,0.65); border:1px solid #3b4048">
            <p class="font-medium mb-1" style="color:#abb2bf">3. Activer le repository</p>
            <p style="color:#abb2bf">Activez-le pour les commits automatiques</p>
          </div>
        </div>
        <div class="pt-3" style="border-top:1px solid #3b4048">
          <p class="text-xs" style="color:#abb2bf">💡 En cas d'échec de suppression, revalidez l'autorisation</p>
        </div>
      </div>`;
        var opts = {
            html: html,
            showCloseButton: true,
            showConfirmButton: false,
            width: 520,
            icon: "info",
            iconColor: "#61afef",
            background: "#1f2937",
            color: "#e5e7eb",
        };
        if (window.Swal) { window.Swal.fire(opts); }
    };

    if (reposSyncBtn) {
        reposSyncBtn.addEventListener("click", async function () {
            if (reposStatus) reposStatus.textContent = "";
            reposSyncBtn.disabled = true;
            reposSyncBtn.classList.add("opacity-75");
            try {
                var r = await fetch((window.APP_BASE || "") + "/github/syncRepositories", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || '') });
                var d = await r.json();
                if (d.success) {
                    var msg =
                        "Synchronisation: créés " +
                        (d.created || 0) +
                        ", mis à jour " +
                        (d.updated || 0) +
                        ", ignorés " +
                        (d.skipped || 0);
                    if (reposStatus) {
                        reposStatus.className = "text-green-400";
                        reposStatus.textContent = msg;
                    }
                    if (window.Swal) {
                        window.Swal.fire({ icon: "success", title: "Synchronisation terminée", text: msg, showConfirmButton: false, timer: 1400, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#98c379" }).then(function(){ location.reload(); });
                    } else {
                        location.reload();
                    }
                } else {
                    var emsg = d.error || "Erreur";
                    if (reposStatus) {
                        reposStatus.className = "text-red-400";
                        reposStatus.textContent = emsg;
                    }
                    if (window.toastError) toastError(emsg);
                }
            } catch (err) {
                if (reposStatus) {
                    reposStatus.className = "text-red-400";
                    reposStatus.textContent = "Erreur réseau";
                }
                if (window.toastError) toastError("Erreur réseau");
            }
            reposSyncBtn.disabled = false;
            reposSyncBtn.classList.remove("opacity-75");
        });
    }

    function parseDateString(str) {
        if (!str) return null;
        var s = String(str).trim();
        if (s.indexOf("T") < 0 && s.indexOf("Z") < 0) {
            s = s.replace(" ", "T");
        }
        return new Date(s);
    }
    function pad(n) {
        return (n < 10 ? "0" : "") + n;
    }
    function formatFrenchDate(str) {
        var d = parseDateString(str);
        if (!d || isNaN(d.getTime())) return String(str);
        return (
            pad(d.getDate()) +
            "/" +
            pad(d.getMonth() + 1) +
            "/" +
            d.getFullYear() +
            " " +
            pad(d.getHours()) +
            ":" +
            pad(d.getMinutes()) +
            ":" +
            pad(d.getSeconds())
        );
    }

    if (sortDateBtn && repoList) {
        var asc = false;
        sortDateBtn.addEventListener("click", function () {
            asc = !asc;
            var items = Array.from(repoList.children);
            items.sort(function (a, b) {
                var ca = a.getAttribute("data-created") || "";
                var cb = b.getAttribute("data-created") || "";
                var ta = parseDateString(ca);
                var tb = parseDateString(cb);
                var taN = ta ? ta.getTime() : 0;
                var tbN = tb ? tb.getTime() : 0;
                return asc ? taN - tbN : tbN - taN;
            });
            items.forEach(function (el) {
                repoList.appendChild(el);
            });
        });
    }

    if (sortSizeBtn && repoList) {
        var ascSize = false;
        sortSizeBtn.addEventListener("click", function () {
            ascSize = !ascSize;
            var items = Array.from(repoList.children);
            items.sort(function (a, b) {
                var sa = parseInt(a.getAttribute("data-size") || "0", 10);
                var sb = parseInt(b.getAttribute("data-size") || "0", 10);
                return ascSize ? sa - sb : sb - sa;
            });
            items.forEach(function (el) {
                repoList.appendChild(el);
            });
        });
    }

    if (sortStarsBtn && repoList) {
        var ascStars = false;
        sortStarsBtn.addEventListener("click", function () {
            ascStars = !ascStars;
            var items = Array.from(repoList.children);
            items.sort(function (a, b) {
                var sa = parseInt(a.getAttribute("data-stars") || "0", 10);
                var sb = parseInt(b.getAttribute("data-stars") || "0", 10);
                return ascStars ? sa - sb : sb - sa;
            });
            items.forEach(function (el) {
                repoList.appendChild(el);
            });
        });
    }

    function langBadgeHTML(lang) {
        var l = String(lang || "").toLowerCase();
        var name = lang || "—";
        var bg = "#abb2bf";
        var fg = "#282c34";
        var ic = '<i class="fa-solid fa-code mr-1"></i>';
        if (l === "php") {
            bg = "#c678dd";
            ic = '<i class="fa-brands fa-php mr-1"></i>';
        } else if (l === "javascript" || l === "js") {
            bg = "#61afef";
            ic = '<i class="fa-brands fa-js mr-1"></i>';
        } else if (l === "typescript" || l === "ts") {
            bg = "#61afef";
            ic = '<i class="fa-brands fa-js mr-1"></i>';
        } else if (l === "python") {
            bg = "#d19a66";
            ic = '<i class="fa-brands fa-python mr-1"></i>';
        } else if (l === "java") {
            bg = "#e06c75";
            ic = '<i class="fa-solid fa-mug-hot mr-1"></i>';
        } else if (l === "html" || l === "html5") {
            bg = "#e06c75";
            ic = '<i class="fa-brands fa-html5 mr-1"></i>';
        } else if (l === "css" || l === "css3") {
            bg = "#98c379";
            ic = '<i class="fa-brands fa-css3 mr-1"></i>';
        } else if (l === "node" || l === "nodejs" || l === "node.js") {
            bg = "#61afef";
            ic = '<i class="fa-brands fa-node-js mr-1"></i>';
        } else if (l === "r") {
            bg = "#c678dd";
            ic = '<i class="fa-brands fa-r-project mr-1"></i>';
        } else if (l === "shell" || l === "bash") {
            bg = "#abb2bf";
            ic = '<i class="fa-solid fa-terminal mr-1"></i>';
        }
        return (
            '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color:' +
            bg +
            ";color:" +
            fg +
            '">' +
            ic +
            name +
            "</span>"
        );
    }

    if ((window.IS_LOGGED || false) && (window.HAS_GITHUB || false) && repoList) {
        fetch((window.APP_BASE || "") + "/github/getReposInfo")
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                var infos = d.repos || [];
                var idx = {};
                infos.forEach(function (i) {
                    idx[String(i.id)] = i;
                });
                Array.from(document.querySelectorAll("[data-id]")).forEach(function (card) {
                    var id = card.getAttribute("data-id");
                    var info = idx[id];
                    if (!info) return;
                    var s = card.querySelector(".repo-size");
                    if (s) {
                        s.textContent = (info.size_kb || 0) + " KB";
                    }
                    var dt = card.querySelector(".repo-date");
                    if (dt) {
                        dt.textContent = formatFrenchDate(info.created_at);
                    }
                    var st = card.querySelector(".repo-stars");
                    if (st) {
                        st.textContent = String(info.stars || 0);
                    }
                    var fk = card.querySelector(".repo-forks");
                    if (fk) {
                        fk.textContent = String(info.forks || 0);
                    }
                    if (info.created_at) {
                        card.setAttribute("data-created", String(info.created_at));
                    }
                    card.setAttribute("data-size", String(info.size_kb || 0));
                    card.setAttribute("data-stars", String(info.stars || 0));
                    var lb = card.querySelector(".repo-lang-badge");
                    if (lb) {
                        lb.innerHTML = langBadgeHTML(info.language || "");
                    }
                });
                // (Définie globalement plus bas)

                window.deleteCurrentTask = async function () {
                    var t = window.__currentTask || null;
                    if (!t || !t.id) return;
                    var ok = window.confirm("Supprimer cette tâche ?");
                    if (!ok) return;
                    try {
                        var res = await fetch((window.APP_BASE || "") + "/kanban/delete", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "id=" + encodeURIComponent(t.id) + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
                        });
                        var d = await res.json();
                        if (d.success) {
                            var card = document.querySelector('[data-id="' + String(t.id) + '"]');
                            if (card && card.parentNode) card.parentNode.removeChild(card);
                            closeTaskModal();
                            if (window.toastSuccess) toastSuccess("Tâche supprimée");
                        } else {
                            var msg = d.error || "Erreur";
                            if (window.toastError) toastError(msg);
                        }
                    } catch (e) {
                        if (window.toastError) toastError("Erreur réseau");
                    }
                };
            })
            .catch(function () {});
    }

    if (broadcastForm) {
        broadcastForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            if (broadcastForm.__busy) return;
            broadcastForm.__busy = true;
            if (broadcastStatus) {
                broadcastStatus.className = "text-blue-400 text-sm inline-flex items-center gap-2";
                broadcastStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Envoi en cours…</span>';
            }
            var fd = new FormData(broadcastForm);
            fd.set("csrf_token", (window.CSRF_TOKEN || ''));
            var btns = broadcastForm.querySelectorAll("button");
            btns.forEach(function (b) {
                b.disabled = true;
                b.classList.add("opacity-75");
            });
            try {
                var res = await fetch((window.APP_BASE || "") + "/dashboard/broadcastTemplate", {
                    method: "POST",
                    body: fd,
                });
                var data = await res.json();
                if (data.success) {
                    var m = "Tâches créées: " + (data.created || 0) + " | Modèles détectés: " + (data.tasks || 0);
                    if (typeof data.eligible !== "undefined") {
                        m += " | Étudiants ciblés: " + (data.eligible || 0);
                    }
                    if (typeof data.missing_count !== "undefined") {
                        m += " | Sans repo actif: " + (data.missing_count || 0);
                    }
                    if (broadcastStatus) {
                        broadcastStatus.className = "text-green-400 text-sm";
                        broadcastStatus.textContent = m;
                    }
                    broadcastForm.reset();
                    if (window.toastSuccess) toastSuccess(m);
                } else {
                    var em = data.error || "Erreur";
                    if (broadcastStatus) {
                        broadcastStatus.className = "text-red-400 text-sm";
                        broadcastStatus.textContent = em;
                    }
                    if (window.toastError) toastError(em);
                }
            } catch (err) {
                if (broadcastStatus) {
                    broadcastStatus.className = "text-red-400 text-sm";
                    broadcastStatus.textContent = "Erreur réseau";
                }
                if (window.toastError) toastError("Erreur réseau");
            }
            btns.forEach(function (b) {
                b.disabled = false;
                b.classList.remove("opacity-75");
            });
            broadcastForm.__busy = false;
        });
    }

    var broadcastStatus = document.getElementById("broadcast-status");

    var broadcastSelect = document.getElementById("broadcast-select");
    var selectMissingFromBtn = document.getElementById("btn-select-missing-from");
    if (selectMissingFromBtn) {
        selectMissingFromBtn.addEventListener("click", function () {
            var boxes = document.querySelectorAll("#students-tbody .stu-checkbox, #students-mobile .stu-checkbox");
            var cnt = 0;
            boxes.forEach(function (b) {
                var t = parseInt(b.getAttribute("data-tasks-count") || "0", 10);
                if (t === 0) {
                    b.checked = true;
                    cnt++;
                }
            });
            if (cnt === 0 && window.toastInfo) toastInfo("Aucun étudiant manquant actuellement");
        });
    }
    var redeploySelectedFromBtn = document.getElementById("btn-redeploy-selected-from");
    if (redeploySelectedFromBtn) {
        redeploySelectedFromBtn.addEventListener("click", async function () {
            if (!broadcastSelect) return;
            var bid = String(broadcastSelect.value || "").trim();
            if (!bid) {
                if (window.toastError) toastError("Sélectionnez une diffusion");
                return;
            }
            var ids = [];
            var boxes = document.querySelectorAll("#students-tbody .stu-checkbox, #students-mobile .stu-checkbox");
            boxes.forEach(function (b) {
                if (b.checked) {
                    ids.push(String(b.value || "").trim());
                }
            });
            if (ids.length === 0) {
                if (window.toastError) toastError("Sélection vide");
                return;
            }
            if (broadcastStatus) {
                broadcastStatus.className = "text-blue-400 text-sm inline-flex items-center gap-2";
                broadcastStatus.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i><span>Redéploiement en cours…</span>';
            }
            var icon = redeploySelectedFromBtn.querySelector("i");
            var iconOrig = icon ? icon.className : "";
            if (icon) {
                icon.className = "fa-solid fa-spinner mr-1 fa-spin";
            }
            redeploySelectedFromBtn.disabled = true;
            redeploySelectedFromBtn.classList.add("opacity-75");
            try {
                var fd = new URLSearchParams();
                fd.append("broadcast_id", bid);
                fd.append("student_ids", ids.join(","));
                var r = await fetch((window.APP_BASE || "") + "/dashboard/redeployFromBroadcast", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: fd.toString() + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
                });
                var d = await r.json();
                if (d && d.success) {
                    var m =
                        "Redéploiement diffusion #" +
                        String(d.broadcast_id || bid) +
                        ": créées " +
                        (d.created || 0) +
                        ", éligibles " +
                        (d.eligible || 0) +
                        ", sélection " +
                        (d.selected_count || 0);
                    if (typeof d.still_missing_count !== "undefined") {
                        m += " | Toujours manquants: " + (d.still_missing_count || 0);
                    }
                    if (broadcastStatus) {
                        broadcastStatus.className = "text-green-400 text-sm";
                        broadcastStatus.textContent = m;
                    }
                    if (window.toastSuccess) toastSuccess(m);
                    var set = new Set(ids);
                    boxes.forEach(function (b) {
                        var id = String(b.value || "").trim();
                        if (set.has(id)) {
                            b.setAttribute("data-tasks-count", "1");
                        }
                    });
                } else {
                    var em = d && d.error ? d.error : "Erreur";
                    if (broadcastStatus) {
                        broadcastStatus.className = "text-red-400 text-sm";
                        broadcastStatus.textContent = em;
                    }
                    if (window.toastError) toastError(em);
                }
            } catch (e) {
                if (broadcastStatus) {
                    broadcastStatus.className = "text-red-400 text-sm";
                    broadcastStatus.textContent = "Erreur réseau";
                }
                if (window.toastError) toastError("Erreur réseau");
            }
            redeploySelectedFromBtn.disabled = false;
            redeploySelectedFromBtn.classList.remove("opacity-75");
            if (icon) {
                icon.className = iconOrig || "fa-solid fa-rotate mr-1";
            }
        });
    }
    var deleteBroadcastBtn = document.getElementById("btn-delete-broadcast");
    if (deleteBroadcastBtn) {
        deleteBroadcastBtn.addEventListener("click", async function () {
            if (!broadcastSelect) return;
            var bid = String(broadcastSelect.value || "").trim();
            if (!bid) {
                if (window.toastError) toastError("Sélectionnez une diffusion");
                return;
            }
            deleteBroadcastBtn.disabled = true;
            deleteBroadcastBtn.classList.add("opacity-75");
            try {
                var fd = new URLSearchParams();
                fd.append("broadcast_id", bid);
                var r = await fetch((window.APP_BASE || "") + "/dashboard/deleteBroadcast", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: fd.toString() + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
                });
                var d = await r.json();
                if (d && d.success) {
                    var m = "Diffusion #" + bid + " supprimée";
                    var idx = broadcastSelect.selectedIndex;
                    if (idx >= 0) {
                        broadcastSelect.remove(idx);
                    }
                    if (window.toastSuccess) toastSuccess(m);
                } else {
                    var em = d && d.error ? d.error : "Erreur";
                    if (window.toastError) toastError(em);
                }
            } catch (e) {
                if (window.toastError) toastError("Erreur réseau");
            }
            deleteBroadcastBtn.disabled = false;
            deleteBroadcastBtn.classList.remove("opacity-75");
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener("click", async function () {
            if (clearStatus) clearStatus.textContent = "";
            var ok = true;
            try {
                ok = await window.confirmAsk({
                    title: "Confirmer la suppression de toutes les tâches ?",
                    icon: "warning",
                });
            } catch (e) {
                ok = false;
            }
            if (!ok) return;
            clearBtn.disabled = true;
            clearBtn.classList.add("opacity-75");
            try {
                var res = await fetch((window.APP_BASE || "") + "/dashboard/clearTasks", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || '') });
                var data = await res.json();
                if (data.success) {
                    var msg = "Tâches supprimées: " + (data.deleted || 0);
                    if (clearStatus) {
                        clearStatus.className = "text-green-400 text-sm";
                        clearStatus.textContent = msg;
                    }
                    if (window.toastSuccess) toastSuccess(msg);
                } else {
                    var emsg = data.error || "Erreur";
                    if (clearStatus) {
                        clearStatus.className = "text-red-400 text-sm";
                        clearStatus.textContent = emsg;
                    }
                    if (window.toastError) toastError(emsg);
                }
            } catch (err) {
                if (clearStatus) {
                    clearStatus.className = "text-red-400 text-sm";
                    clearStatus.textContent = "Erreur réseau";
                }
                if (window.toastError) toastError("Erreur réseau");
            }
            clearBtn.disabled = false;
            clearBtn.classList.remove("opacity-75");
        });
    }

    if (openBroadcast) {
        openBroadcast.addEventListener("click", function () {
            if (broadcastModal) { openModal(broadcastModal); }
        });
    }

    window.closeBroadcastModal = function () {
        if (broadcastModal) { closeModal(broadcastModal); }
    };

    window.openCommitsModal = function (btn) {
        var uid = btn.getAttribute("data-user-id");
        var name = btn.getAttribute("data-user-name");
        if (commitsTitle) commitsTitle.textContent = "Commits de " + name;
        if (commitsList) commitsList.innerHTML = "";
        if (commitsSelect) commitsSelect.innerHTML = "";
        if (commitsModal) { openModal(commitsModal); }
        var ids = commitNotifIdsByStudent[parseInt(uid || 0, 10)] || [];
        ids.forEach(function (id) {
            try {
                fetch((window.APP_BASE || "") + "/dashboard/markNotificationRead", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    credentials: "same-origin",
                    body: "id=" + encodeURIComponent(id || 0) + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
                })
                    .then(function () {})
                    .catch(function () {});
            } catch (e) {}
        });
        Array.from(document.querySelectorAll('.commit-badge[data-student-id="' + uid + '"]')).forEach(function (el) {
            if (el && el.parentNode) el.parentNode.removeChild(el);
        });
        fetch((window.APP_BASE || "") + "/dashboard/getStudentRepos?user_id=" + encodeURIComponent(uid), {
            credentials: "same-origin",
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                var repos = d.repos || [];
                if (repos.length === 0) {
                    if (commitsList) commitsList.innerHTML = '<div class="text-gray-400">Aucun repository lié</div>';
                    return;
                }
                repos.forEach(function (rp) {
                    var opt = document.createElement("option");
                    opt.value = String(rp.id);
                    opt.textContent = rp.name || rp.github_url || "Repo #" + rp.id;
                    if (commitsSelect) commitsSelect.appendChild(opt);
                });
                if (commitsSelect) loadCommits(uid, commitsSelect.value);
            });
    };

    function loadCommits(uid, rid) {
        if (!uid || !rid) {
            if (commitsList) commitsList.innerHTML = "";
            return;
        }
        if (commitsList) commitsList.innerHTML = '<div class="text-gray-300 text-sm">Chargement...</div>';
        if (commitsAbort) {
            try {
                commitsAbort.abort();
            } catch (e) {}
        }
        commitsAbort = new AbortController();
        fetch(
            (window.APP_BASE || "") +
                "/github/getCommits?user_id=" +
                encodeURIComponent(uid) +
                "&repo_id=" +
                encodeURIComponent(rid),
            { signal: commitsAbort.signal, credentials: "same-origin" }
        )
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                var cs = d.commits || [];
                if (commitsList) commitsList.innerHTML = "";
                if (cs.length === 0) {
                    if (commitsList) commitsList.innerHTML = '<div class="text-gray-400">Aucun commit</div>';
                    return;
                }
                cs.forEach(function (c) {
                    var row = document.createElement("div");
                    row.className = "bg-gray-700 p-3 rounded";
                    var a = document.createElement("a");
                    a.href = c.html_url || "#";
                    a.target = "_blank";
                    a.className = "text-blue-400";
                    a.textContent = c.sha.substring(0, 7);
                    var m = document.createElement("div");
                    m.className = "text-sm text-gray-100";
                    m.textContent = c.message;
                    var dt = document.createElement("div");
                    dt.className = "text-xs text-gray-400";
                    dt.textContent = formatFrenchDate(c.date || "");
                    row.appendChild(a);
                    row.appendChild(m);
                    row.appendChild(dt);
                    if (commitsList) commitsList.appendChild(row);
                });
            })
            .catch(function () {});
    }

    if (commitsSelect) {
        commitsSelect.addEventListener("change", function () {
            var rid = commitsSelect.value;
            var title = commitsTitle ? commitsTitle.textContent || "" : "";
            var name = title.replace(/^Commits de\s*/, "");
            var btn = document.querySelector('button[data-user-name="' + name + '"]');
            var uid = btn ? btn.getAttribute("data-user-id") : null;
            loadCommits(uid, rid);
        });
    }

    window.closeCommitsModal = function () {
        if (commitsModal) { closeModal(commitsModal); }
    };

    var search = document.getElementById("students-search");
    var tbody = document.getElementById("students-tbody");
    var mobile = document.getElementById("students-mobile");
    var commitNotifIdsByStudent = {};

    async function refreshCommitBadges() {
        if (!window.IS_LOGGED || window.USER_ROLE !== "formateur") { return; }
        try {
            var r = await fetch((window.APP_BASE || "") + "/dashboard/getNotifications", {
                credentials: "same-origin",
            });
            if (!r.ok) return;
            var d = await r.json();
            var items = d && d.items ? d.items : [];
            Array.from(document.querySelectorAll(".commit-badge")).forEach(function (el) {
                if (el && el.parentNode) el.parentNode.removeChild(el);
            });
            commitNotifIdsByStudent = {};
            var counts = {};
            items.forEach(function (n) {
                var t = n.type || "";
                var read = n.is_read || 0;
                if (t !== "commit" || read === 1) return;
                var data = {};
                try {
                    data = JSON.parse(n.data || "{}");
                } catch (e) {
                    data = {};
                }
                var sid = parseInt(data.student_id || 0, 10);
                var c = parseInt(data.commit_count || 1, 10);
                if (!sid) return;
                counts[sid] = (counts[sid] || 0) + c;
                commitNotifIdsByStudent[sid] = commitNotifIdsByStudent[sid] || [];
                commitNotifIdsByStudent[sid].push(n.id);
            });
            Object.keys(counts).forEach(function (sidStr) {
                var sid = parseInt(sidStr, 10);
                var count = counts[sid];
                var btns = document.querySelectorAll('button[data-user-id="' + sid + '"]');
                btns.forEach(function (btn) {
                    var b = document.createElement("span");
                    b.className =
                        "commit-badge ml-2 inline-flex items-center justify-center rounded-full bg-red-600 text-white text-xs px-1";
                    b.setAttribute("data-student-id", String(sid));
                    b.textContent = "+" + String(count || 1);
                    btn.appendChild(b);
                });
            });
        } catch (e) {}
    }

    if (window.IS_LOGGED && window.USER_ROLE === "formateur") {
        setInterval(refreshCommitBadges, 12000);
        refreshCommitBadges();
    }

    function debounce(fn, delay) {
        var t;
        return function () {
            var ctx = this,
                args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    }

    if (search) {
        search.addEventListener(
            "input",
            debounce(function () {
                var q = search.value.toLowerCase();
                if (tbody) {
                    Array.from(tbody.querySelectorAll("tr")).forEach(function (tr) {
                        var tds = tr.querySelectorAll("td");
                        var name = tr.querySelector("span") ? tr.querySelector("span").textContent.toLowerCase() : "";
                        var gh = tr.querySelector("a") ? tr.querySelector("a").textContent.toLowerCase() : "";
                        var show = q === "" || name.indexOf(q) >= 0 || gh.indexOf(q) >= 0;
                        tr.style.display = show ? "" : "none";
                    });
                }
                if (mobile) {
                    Array.from(mobile.querySelectorAll(".p-3.rounded")).forEach(function (card) {
                        var nameEl = card.querySelector(".font-semibold");
                        var ghEl = card.querySelector("a");
                        var name = (nameEl ? nameEl.textContent : "").toLowerCase();
                        var gh = (ghEl ? ghEl.textContent : "").toLowerCase();
                        var show = q === "" || name.indexOf(q) >= 0 || gh.indexOf(q) >= 0;
                        card.style.display = show ? "" : "none";
                    });
                }
            }, 150)
        );
    }
})();