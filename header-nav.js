/* ==========================================================================
   FICHIER : header-nav.js
   RÔLE : Initialiser l'en-tête commun (onglet actif, menu hamburger mobile 3 tirets,
          redirections propres sans erreur, et recherche automatique au fil de la saisie).
   ========================================================================== */
function initialiserEnTeteCommun(options = {}) {
    const conteneur = document.getElementById("header-container");
    if (!conteneur) return;

    // 1. Détection de la page active (Desktop + Mobile)
    const chemin = window.location.pathname.toLowerCase();
    const estPageMemoires = chemin.includes("memoires.html");

    conteneur.querySelectorAll('[data-page-nav="memoires"]').forEach(el => {
        el.classList.toggle("actif", estPageMemoires);
    });
    conteneur.querySelectorAll('[data-page-nav="accueil"]').forEach(el => {
        el.classList.toggle("actif", !estPageMemoires);
    });

    // 2. Menu hamburger mobile (3 tirets horizontaux à droite dans le bleu)
    const btnMenu = conteneur.querySelector("#btn-menu-mobile");
    const menuDeroulant = conteneur.querySelector("#menu-mobile-deroulant");

    if (btnMenu && menuDeroulant) {
        btnMenu.addEventListener("click", (e) => {
            e.stopPropagation();
            const ouvert = btnMenu.getAttribute("aria-expanded") === "true";
            btnMenu.setAttribute("aria-expanded", String(!ouvert));
            menuDeroulant.hidden = ouvert;
        });

        document.addEventListener("click", (e) => {
            if (!menuDeroulant.hidden && !conteneur.contains(e.target)) {
                btnMenu.setAttribute("aria-expanded", "false");
                menuDeroulant.hidden = true;
            }
        });
    }

    // 3. Gestion propre du lien Connexion pour éviter toute erreur 404 tant que la page n'est pas créée
    conteneur.querySelectorAll('[data-connexion-link="true"]').forEach(lien => {
        lien.addEventListener("click", async (e) => {
            e.preventDefault();
            if (menuDeroulant) {
                menuDeroulant.hidden = true;
                btnMenu?.setAttribute("aria-expanded", "false");
            }
            try {
                const test = await fetch("connexion.html", { method: "HEAD" });
                if (test.ok) {
                    window.location.href = "connexion.html";
                    return;
                }
            } catch (_) {}

            let toast = document.getElementById("toast-connexion-info");
            if (!toast) {
                toast = document.createElement("div");
                toast.id = "toast-connexion-info";
                toast.style.cssText = [
                    "position:fixed", "top:20px", "left:50%", "transform:translateX(-50%)",
                    "background:#102b50", "color:#fff", "padding:12px 20px",
                    "border-radius:12px", "font-family:Arial,sans-serif", "font-size:14px",
                    "font-weight:700", "box-shadow:0 10px 28px rgba(0,0,0,0.25)",
                    "z-index:9999", "text-align:center", "max-width:90%"
                ].join(";");
                toast.textContent = "L'espace Connexion sera disponible prochainement.";
                document.body.appendChild(toast);
            }
            toast.hidden = false;
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => { toast.hidden = true; }, 3200);
        });
    });

    // 4. Recherche automatique au fil de la saisie (header desktop + barre mobile sur le héros)
    const champsRecherche = [
        conteneur.querySelector("#header-search-input"),
        document.getElementById("hero-search-mobile-input")
    ].filter(Boolean);

    const paramsUrl = new URLSearchParams(window.location.search);
    const qInitial = paramsUrl.get("q") || "";

    champsRecherche.forEach(champ => {
        if (qInitial && !champ.value) champ.value = qInitial;

        let chrono;
        champ.addEventListener("input", () => {
            const valeur = champ.value;
            // Synchroniser les champs entre eux
            champsRecherche.forEach(autre => {
                if (autre !== champ) autre.value = valeur;
            });

            clearTimeout(chrono);
            chrono = setTimeout(() => {
                if (typeof options.onSearchInput === "function") {
                    options.onSearchInput(valeur);
                } else {
                    const texte = valeur.trim();
                    if (texte.length >= 1) {
                        window.location.href = "memoires.html?q=" + encodeURIComponent(texte);
                    }
                }
            }, 350);
        });
    });
}
