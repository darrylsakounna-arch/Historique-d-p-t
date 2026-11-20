function initialiserTelephone() {
    const telephone = document.getElementById("telephone");

    if (!telephone) return;

    const PREFIXE = "01";

    function formaterTelephone(valeur) {
        let chiffres = valeur.replace(/\D/g, "");

        if (chiffres.startsWith(PREFIXE)) {
            chiffres = chiffres.slice(2);
        }

        chiffres = chiffres.slice(0, 8);

        const paires = chiffres.match(/\d{1,2}/g) || [];

        return [PREFIXE, ...paires].join(" ");
    }

    telephone.addEventListener("input", () => {
        telephone.value = formaterTelephone(telephone.value);
        telephone.setCustomValidity("");
    });

    telephone.addEventListener("focus", () => {
        if (!telephone.value) {
            telephone.value = PREFIXE;
        }
    });

    telephone.addEventListener("keydown", (e) => {
        const debut = telephone.selectionStart;
        const fin = telephone.selectionEnd;

        if (
            (e.key === "Backspace" && fin <= 2 && debut === fin) ||
            (e.key === "Delete" && debut < 2)
        ) {
            e.preventDefault();
        }
    });

    telephone.form?.addEventListener("submit", (e) => {
        const nombreChiffres = telephone.value.replace(/\D/g, "").length;

        if (nombreChiffres !== 10) {
            e.preventDefault();

            telephone.setCustomValidity(
                "Le numéro doit contenir exactement 10 chiffres."
            );

            telephone.reportValidity();
        } else {
            telephone.setCustomValidity("");
        }
    });
}



function montrer(champ, visible) {
    if (!champ) return;

    const bloc = champ.closest(".champ") || champ;

    bloc.hidden = !visible;
    champ.disabled = !visible;
    champ.required = visible;

    if (!visible) {
        champ.value = "";
    }
}



function initialiserNaissance() {
    const naissance = document.getElementById("naissance");

    if (!naissance) return;

    const aujourdhui = new Date();

    const annee = aujourdhui.getFullYear();
    const mois = String(aujourdhui.getMonth() + 1).padStart(2, "0");
    const jour = String(aujourdhui.getDate()).padStart(2, "0");

    naissance.min = "1900-01-01";
    naissance.max = `${annee}-${mois}-${jour}`;
}



function initialiserAcademique() {
    const universite = document.getElementById("universite");

    if (!universite) return;

    const universiteAutre = document.getElementById("universite-autre");
    const entiteAutre = document.getElementById("entite-autre");
    const diplome = document.getElementById("diplome");
    const diplomeAutre = document.getElementById("diplome-autre");
    const annee = document.getElementById("annee-soutenance");

    const entites = document.querySelectorAll(
        'select[id^="entite-"]'
    );

    
    function actualiserUniversite() {
        const choix = universite.value.toLowerCase();

        const estAutre = choix === "autre";

        montrer(universiteAutre, estAutre);
        montrer(entiteAutre, estAutre);

        entites.forEach((entite) => {
            montrer(
                entite,
                entite.id === "entite-" + choix
            );
        });
    }

    universite.addEventListener("change", actualiserUniversite);

    actualiserUniversite();


    
    if (diplome) {
        function actualiserDiplome() {
            montrer(
                diplomeAutre,
                diplome.value.toLowerCase() === "autre"
            );
        }

        diplome.addEventListener("change", actualiserDiplome);

        actualiserDiplome();
    }


    
    if (annee) {
        annee.innerHTML = '<option value="">Choisir</option>';

        const ANNEE_DEBUT = 1970;
        const ANNEE_ACTUELLE = new Date().getFullYear();

        for (
            let debut = ANNEE_ACTUELLE;
            debut >= ANNEE_DEBUT;
            debut--
        ) {
            const texte = `${debut}-${debut + 1}`;

            annee.add(new Option(texte, texte));
        }
    }
}



document.addEventListener("DOMContentLoaded", () => {
    initialiserTelephone();
    initialiserNaissance();
    initialiserAcademique();
});

document.addEventListener("DOMContentLoaded", () => {
    const pageActuelle = document.body.dataset.page;
    const etapes = document.querySelectorAll(".progression .etape");
    const lignes = document.querySelectorAll(".progression .ligne-progression");

    if (!pageActuelle) return;

    etapes.forEach((etape, index) => {
        const nomEtape = etape.dataset.step;
        const terminee = sessionStorage.getItem("depotMemoire_" + nomEtape) === "true";

        etape.classList.toggle("active", nomEtape === pageActuelle);
        etape.classList.toggle("terminee", terminee);

        if (lignes[index]) {
            lignes[index].classList.toggle("terminee", terminee);
        }
    });

    const formulaire = document.querySelector(".formulaire");

    if (formulaire) {
        formulaire.addEventListener("submit", () => {
        if (pageActuelle !== "documents") {
        sessionStorage.setItem("depotMemoire_" + pageActuelle, "true");
    }
});
    }

    const liensPrecedents = document.querySelectorAll(".precedentButton");

    liensPrecedents.forEach(lien => {
        lien.addEventListener("click", () => {
            sessionStorage.removeItem("depotMemoire_" + pageActuelle);
        });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const formulaire = document.querySelector(".formulaire");
    const pageActuelle = document.body.dataset.page;

    if (!formulaire || !pageActuelle) return;

    const cle = "depotMemoire_formulaire_" + pageActuelle;

    function sauvegarderInformations() {
        const donnees = {};

        Array.from(formulaire.elements).forEach(champ => {
            if (
                !champ.name ||
                champ.type === "file" ||
                champ.type === "submit" ||
                champ.type === "button" ||
                champ.disabled
            ) {
                return;
            }

            const identifiant = champ.id || champ.name;

            if (champ.type === "checkbox") {
                donnees[identifiant] = champ.checked;
            } else if (champ.type === "radio") {
                if (champ.checked) donnees[identifiant] = champ.value;
            } else {
                donnees[identifiant] = champ.value;
            }
        });

        sessionStorage.setItem(cle, JSON.stringify(donnees));
    }

    function restaurerInformations() {
        let donnees = {};

        try {
            donnees = JSON.parse(sessionStorage.getItem(cle) || "{}");
        } catch (erreur) {
            donnees = {};
        }

        ["universite", "diplome"].forEach(id => {
            const champ = document.getElementById(id);

            if (champ && donnees[id] !== undefined) {
                champ.value = donnees[id];
                champ.dispatchEvent(new Event("change", { bubbles: true }));
            }
        });

        Array.from(formulaire.elements).forEach(champ => {
            const identifiant = champ.id || champ.name;

            if (
                !identifiant ||
                donnees[identifiant] === undefined ||
                champ.type === "file" ||
                champ.type === "submit" ||
                champ.type === "button" ||
                champ.disabled
            ) {
                return;
            }

            if (champ.type === "checkbox") {
                champ.checked = donnees[identifiant];
            } else if (champ.type === "radio") {
                champ.checked = champ.value === donnees[identifiant];
            } else {
                champ.value = donnees[identifiant];
            }
        });
    }

    restaurerInformations();

    formulaire.addEventListener("input", sauvegarderInformations);
    formulaire.addEventListener("change", sauvegarderInformations);
    formulaire.addEventListener("submit", sauvegarderInformations);
});

document.addEventListener("DOMContentLoaded", () => {
    const fichiers = [
        "pv_soutenance",
        "attestation",
        "preuve_plagiat",
        "memoire_corrige"
    ];

    fichiers.forEach(id => {
        const champ = document.getElementById(id);

        if (!champ) return;

        const ligne = document.createElement("div");
        ligne.className = "ligne-fichier";

        champ.parentNode.insertBefore(ligne, champ);
        ligne.appendChild(champ);

        const lien = document.createElement("a");
        lien.className = "voir-fichier";
        lien.textContent = "Voir";
        lien.target = "_blank";
        lien.rel = "noopener";
        lien.hidden = true;

        ligne.appendChild(lien);

        let ancienneUrl = null;

        champ.addEventListener("change", () => {
            if (ancienneUrl) {
                URL.revokeObjectURL(ancienneUrl);
                ancienneUrl = null;
            }

            const fichier = champ.files[0];

            if (!fichier) {
                lien.hidden = true;
                lien.removeAttribute("href");
                return;
            }

            if (fichier.type !== "application/pdf") {
                lien.hidden = true;
                lien.removeAttribute("href");
                return;
            }

            ancienneUrl = URL.createObjectURL(fichier);
            lien.href = ancienneUrl;
            lien.hidden = false;
        });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const jour = document.getElementById("jour");
    const mois = document.getElementById("mois");
    const annee = document.getElementById("annee");

    if (!jour || !mois || !annee) return;

    const dateActuelle = new Date();
    const anneeActuelle = dateActuelle.getFullYear();

    // Remplir les années
    for (let a = anneeActuelle; a >= 1900; a--) {
        annee.add(new Option(a, a));
    }

    function actualiserJours() {
        const ancienJour = jour.value;
        const moisChoisi = Number(mois.value);
        const anneeChoisie = Number(annee.value);

        let nombreJours = 31;

        if (moisChoisi && anneeChoisie) {
            nombreJours = new Date(
                anneeChoisie,
                moisChoisi,
                0
            ).getDate();
        }

        if (moisChoisi && anneeChoisie === anneeActuelle) {
            if (moisChoisi === dateActuelle.getMonth() + 1) {
                nombreJours = Math.min(
                    nombreJours,
                    dateActuelle.getDate()
                );
            } else if (moisChoisi > dateActuelle.getMonth() + 1) {
                nombreJours = 0;
            }
        }

        jour.innerHTML = '<option value="">Jour</option>';

        for (let j = 1; j <= nombreJours; j++) {
            jour.add(new Option(j, j));
        }

        if (ancienJour && Number(ancienJour) <= nombreJours) {
            jour.value = ancienJour;
        }
    }

    mois.addEventListener("change", actualiserJours);
    annee.addEventListener("change", actualiserJours);

    // Restaurer la date après avoir créé les options
    let donnees = {};

    try {
        donnees = JSON.parse(
            sessionStorage.getItem(
                "depotMemoire_formulaire_personnel"
            ) || "{}"
        );
    } catch (erreur) {
        donnees = {};
    }

    if (donnees.annee !== undefined) {
        annee.value = donnees.annee;
    }

    if (donnees.mois !== undefined) {
        mois.value = donnees.mois;
    }

    actualiserJours();

    if (donnees.jour !== undefined) {
        jour.value = donnees.jour;
    }
});

/* =====================================================================
   ENVOI RÉEL VERS traitement.php + GESTION DES ERREURS
   ===================================================================== */

const CLE_ERREURS = "depotMemoire_erreurs";

const PAGES_ETAPES = {
    personnel: "personnel.html",
    academique: "academique.html",
    superviseur: "superviseur.html",
    documents: "document.html"
};

const LIMITES_FICHIERS_MO = {
    pv_soutenance: 2,
    attestation: 2,
    preuve_plagiat: 2,
    memoire_corrige: 50
};

/* ---------- Stockage des erreurs (sessionStorage) ---------- */

function lireErreurs() {
    try {
        return JSON.parse(sessionStorage.getItem(CLE_ERREURS) || "{}");
    } catch (erreur) {
        return {};
    }
}

function ecrireErreurs(erreurs) {
    Object.keys(erreurs).forEach(page => {
        if (!Object.keys(erreurs[page] || {}).length) delete erreurs[page];
    });

    if (Object.keys(erreurs).length) {
        sessionStorage.setItem(CLE_ERREURS, JSON.stringify(erreurs));
    } else {
        sessionStorage.removeItem(CLE_ERREURS);
    }
}

/* ---------- Affichage des messages sous les champs ---------- */

function afficherErreurChamp(id, message) {
    const champ = document.getElementById(id);

    if (!champ) return false;

    const bloc = champ.closest(".champ, .form-group") || champ.parentNode;

    let msg = bloc.querySelector(".message-erreur");

    if (!msg) {
        msg = document.createElement("p");
        msg.className = "message-erreur";
        msg.setAttribute("role", "alert");
        bloc.appendChild(msg);
    }

    msg.textContent = message;
    champ.classList.add("champ-erreur");

    return true;
}

function retirerAffichageErreur(id) {
    const champ = document.getElementById(id);

    if (!champ) return;

    champ.classList.remove("champ-erreur");

    const bloc = champ.closest(".champ, .form-group") || champ.parentNode;
    const msg = bloc.querySelector(".message-erreur");

    if (msg) msg.remove();
}

function afficherErreurGenerale(message) {
    const formulaire = document.querySelector(".formulaire");

    if (!formulaire) return;

    let bandeau = formulaire.querySelector(".message-erreur-general");

    if (!bandeau) {
        bandeau = document.createElement("div");
        bandeau.className = "message-erreur-general";
        bandeau.setAttribute("role", "alert");
        formulaire.prepend(bandeau);
    }

    bandeau.textContent = message;
}

function retirerErreurGenerale() {
    document.querySelector(".message-erreur-general")?.remove();
}

function focusPremiereErreur() {
    const champ = document.querySelector(".champ-erreur");

    if (!champ) return;

    champ.scrollIntoView({ behavior: "smooth", block: "center" });
    champ.focus({ preventScroll: true });
}

/* ---------- Suppression d'une erreur quand l'apprenant corrige ---------- */

function effacerErreur(id) {
    const page = document.body.dataset.page;

    if (!page) return;

    // La date de naissance a une seule erreur, rattachée au champ "jour"
    const cle = ["jour", "mois", "annee"].includes(id) && page === "personnel" ? "jour" : id;

    retirerAffichageErreur(cle);

    const erreurs = lireErreurs();

    if (erreurs[page] && erreurs[page][cle] !== undefined) {
        delete erreurs[page][cle];
        ecrireErreurs(erreurs);
        actualiserProgression();
    }
}

/* ---------- Icônes de la barre de progression ---------- */

function actualiserProgression() {
    const erreurs = lireErreurs();
    const uneErreurExiste = Object.keys(erreurs).length > 0;

    document.querySelectorAll(".progression .etape").forEach(etape => {
        const nom = etape.dataset.step;
        const coche = etape.querySelector(".coche-etape");

        const enErreur = !!(erreurs[nom] && Object.keys(erreurs[nom]).length);
        const cliquable =  true;                        /* enErreur || (nom === "documents" && uneErreurExiste); */

        etape.classList.toggle("erreur", enErreur);
        etape.classList.toggle("cliquable", cliquable);

        if (coche) coche.textContent = enErreur ? "!" : "✓";

        if (cliquable) {
            etape.setAttribute("role", "link");
            etape.tabIndex = 0;
            etape.title = enErreur
                ? "Erreur détectée : cliquez pour la corriger"
                : "Retourner à l'envoi des documents";
        } else {
            etape.removeAttribute("role");
            etape.removeAttribute("tabindex");
            etape.removeAttribute("title");
        }
    });
}

function allerVersEtape(nom) {
    if (nom === document.body.dataset.page) {
        focusPremiereErreur();
    } else if (PAGES_ETAPES[nom]) {
        window.location.href = PAGES_ETAPES[nom];
    }
}

/* ---------- Collecte des informations des étapes précédentes ---------- */

function collecterDonnees() {
    const donnees = {};

    ["personnel", "academique", "superviseur"].forEach(page => {
        try {
            donnees[page] = JSON.parse(
                sessionStorage.getItem("depotMemoire_formulaire_" + page) || "{}"
            );
        } catch (erreur) {
            donnees[page] = {};
        }
    });

    return donnees;
}

// Vide à la fois sessionStorage et tous les fichiers PDF sauvegardés dans IndexedDB
function viderFichiersIndexedDB() {
    return new Promise((resolve) => {
        try {
            const demande = indexedDB.open("depotMemoireFichiers", 1);
            demande.onupgradeneeded = () => {
                const db = demande.result;
                if (!db.objectStoreNames.contains("fichiers")) {
                    db.createObjectStore("fichiers");
                }
            };
            demande.onsuccess = () => {
                const db = demande.result;
                if (!db.objectStoreNames.contains("fichiers")) {
                    db.close();
                    resolve();
                    return;
                }
                const tx = db.transaction("fichiers", "readwrite");
                tx.objectStore("fichiers").clear();
                tx.oncomplete = () => { db.close(); resolve(); };
                tx.onerror = () => { db.close(); resolve(); };
            };
            demande.onerror = () => resolve();
        } catch (e) {
            resolve();
        }
    });
}

async function viderCacheDepot() {
    Object.keys(sessionStorage)
        .filter(cle => cle.startsWith("depotMemoire_"))
        .forEach(cle => sessionStorage.removeItem(cle));
    await viderFichiersIndexedDB();
}

/* ---------- Initialisation ---------- */

document.addEventListener("DOMContentLoaded", () => {
    const page = document.body.dataset.page;

    if (!page) return;

    /* 1. Réaffiche les erreurs de CETTE page */
    const erreurs = lireErreurs();
    const erreursPage = erreurs[page] || {};

    Object.keys(erreursPage).forEach(id => {
        if (id === "_general") {
            afficherErreurGenerale(erreursPage[id]);
            return;
        }

        const champ = document.getElementById(id);

        // Champ masqué (ex. une autre université choisie depuis) : l'erreur n'a plus de sens
        if (champ && (champ.disabled || champ.closest(".champ")?.hidden)) {
            delete erreurs[page][id];
            return;
        }

        afficherErreurChamp(id, erreursPage[id]);
    });

    ecrireErreurs(erreurs);

    /* 2. Icônes : ✓ ou !, et clic pour revenir corriger */
    actualiserProgression();

    const progression = document.querySelector(".progression");

    progression?.addEventListener("click", e => {
        const etape = e.target.closest(".etape.cliquable");
        if (etape) allerVersEtape(etape.dataset.step);
    });

    progression?.addEventListener("keydown", e => {
        if (e.key !== "Enter" && e.key !== " ") return;
        const etape = e.target.closest(".etape.cliquable");
        if (etape) {
            e.preventDefault();
            allerVersEtape(etape.dataset.step);
        }
    });

    if (document.querySelector(".champ-erreur")) {
        setTimeout(focusPremiereErreur, 150);
    }

    /* 3. L'erreur disparaît dès que l'apprenant modifie le champ */
    document.addEventListener("input", e => e.target.id && effacerErreur(e.target.id));
    document.addEventListener("change", e => e.target.id && effacerErreur(e.target.id));

    /* 4. Si l'apprenant clique sur "Suivant", on considère la page revue */
    if (page !== "documents") {
        document.querySelector(".formulaire")?.addEventListener("submit", () => {
            const restantes = lireErreurs();
            delete restantes[page];
            ecrireErreurs(restantes);
        });
    }
});

/* ---------- Page « documents » : contrôle des tailles + envoi AJAX ---------- */

document.addEventListener("DOMContentLoaded", () => {
    const formulaire = document.querySelector(".documents-formulaire");

    if (!formulaire) return;

    function messageTaille(fichier, maxMo) {
        const tailleMo = (fichier.size / 1048576).toFixed(2).replace(".", ",");
        return `Fichier trop volumineux (${tailleMo} Mo) : maximum ${maxMo} Mo.`;
    }

    /* Contrôle immédiat dès le choix du fichier */
    Object.entries(LIMITES_FICHIERS_MO).forEach(([id, maxMo]) => {
        const champ = document.getElementById(id);

        if (!champ) return;

        champ.addEventListener("change", (e) => {
            const fichier = champ.files[0];

            if (fichier && fichier.size > maxMo * 1048576) {
                e.stopPropagation(); // évite que l'écouteur global efface aussitôt le message
                afficherErreurChamp(id, messageTaille(fichier, maxMo));
                champ.value = "";
                champ.closest(".ligne-fichier")?.querySelector(".voir-fichier")?.setAttribute("hidden", "");
            }
        });
    });

    formulaire.addEventListener("submit", async (e) => {
        e.preventDefault();
        retirerErreurGenerale();

        if (!formulaire.checkValidity()) {
            formulaire.reportValidity();
            return;
        }

        /* Contrôle des tailles avant d'envoyer (évite d'envoyer 50 Mo pour rien) */
        let tailleInvalide = false;

        Object.entries(LIMITES_FICHIERS_MO).forEach(([id, maxMo]) => {
            const fichier = document.getElementById(id)?.files[0];

            if (fichier && fichier.size > maxMo * 1048576) {
                afficherErreurChamp(id, messageTaille(fichier, maxMo));
                tailleInvalide = true;
            }
        });

        if (tailleInvalide) {
            focusPremiereErreur();
            return;
        }

        const bouton = formulaire.querySelector('button[type="submit"]');
        const texteBouton = bouton ? bouton.textContent : "";

        if (bouton) {
            bouton.disabled = true;
            bouton.textContent = "Envoi en cours…";
        }

        const donneesEnvoi = new FormData(formulaire);
        donneesEnvoi.append("donnees", JSON.stringify(collecterDonnees()));

        try {
            const reponse = await fetch(formulaire.action, {
                method: "POST",
                body: donneesEnvoi
            });

            let resultat = null;

            try {
                resultat = await reponse.json();
            } catch (erreur) {
                resultat = null;
            }

            if (resultat && resultat.ok) {
                // Supprime définitivement les informations texte (sessionStorage)
                // ET les fichiers PDF sauvegardés (IndexedDB) pour que le prochain dépôt soit vierge
                await viderCacheDepot();
                formulaire.reset();

                const badge = document.createElement("div");
                badge.className = "badge-confirmation";

                badge.innerHTML = `
                    <div class="confirmation-contenu">
                        <div class="confirmation-icone">✓</div>
                        <h2>Votre dossier a été soumis !</h2>
                        <p>Votre soumission a bien été prise en compte.</p>
                        <span>Confirmation réussie</span>
                    </div>
                `;

                document.body.appendChild(badge);

                setTimeout(() => {
                    window.location.href = resultat.redirect || "confirmation.html";
                }, 3000);

                return;
            }

            /* Échec : on enregistre les erreurs et on les affiche */
            const erreursRecues = (resultat && resultat.errors) || {
                documents: {
                    _general: (resultat && resultat.message) || "Une erreur est survenue sur le serveur."
                }
            };

            ecrireErreurs(erreursRecues);

            const erreursDocuments = erreursRecues.documents || {};

            Object.keys(erreursDocuments).forEach(id => {
                if (id === "_general") {
                    afficherErreurGenerale(erreursDocuments[id]);
                } else {
                    afficherErreurChamp(id, erreursDocuments[id]);
                }
            });

            const autresPages = Object.keys(erreursRecues).filter(p => p !== "documents");

            if (autresPages.length) {
                afficherErreurGenerale(
                    "Des informations des étapes précédentes sont à corriger : cliquez sur les icônes marquées d'un « ! » en haut de la page."
                );
            }

            actualiserProgression();
            focusPremiereErreur();
        } catch (erreur) {
            afficherErreurGenerale("Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.");
        }

        if (bouton) {
            bouton.disabled = false;
            bouton.textContent = texteBouton;
        }
    });
});

/* =====================================================
   CONSERVATION DES FICHIERS AVEC INDEXEDDB
   ===================================================== */

document.addEventListener("DOMContentLoaded", async () => {

    const formulaire = document.querySelector(".documents-formulaire");

    if (!formulaire) return;

    const identifiants = [
        "pv_soutenance",
        "attestation",
        "preuve_plagiat",
        "memoire_corrige"
    ];

    const NOM_BASE = "depotMemoireFichiers";
    const NOM_TABLE = "fichiers";

    // Ouvrir la base de données
    function ouvrirBase() {
        return new Promise((resolve, reject) => {

            const demande = indexedDB.open(NOM_BASE, 1);

            demande.onupgradeneeded = () => {
                const db = demande.result;

                if (!db.objectStoreNames.contains(NOM_TABLE)) {
                    db.createObjectStore(NOM_TABLE);
                }
            };

            demande.onsuccess = () => resolve(demande.result);
            demande.onerror = () => reject(demande.error);

        });
    }

    const db = await ouvrirBase();

    // Enregistrer un fichier
    function enregistrerFichier(id, fichier) {
        return new Promise((resolve, reject) => {

            const transaction = db.transaction(NOM_TABLE, "readwrite");

            transaction.objectStore(NOM_TABLE).put(fichier, id);

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);

        });
    }

    // Récupérer un fichier
    function recupererFichier(id) {
        return new Promise((resolve, reject) => {

            const transaction = db.transaction(NOM_TABLE, "readonly");

            const demande = transaction.objectStore(NOM_TABLE).get(id);

            demande.onsuccess = () => resolve(demande.result);
            demande.onerror = () => reject(demande.error);

        });
    }

    // Supprimer un fichier remplacé ou retiré
    function supprimerFichier(id) {
        return new Promise((resolve, reject) => {

            const transaction = db.transaction(NOM_TABLE, "readwrite");

            transaction.objectStore(NOM_TABLE).delete(id);

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);

        });
    }

    // Désactiver temporairement le bouton de soumission
    const bouton = formulaire.querySelector('button[type="submit"]');

    if (bouton) bouton.disabled = true;

    try {

        // Restaurer les fichiers déjà enregistrés
        for (const id of identifiants) {

            const champ = document.getElementById(id);

            if (!champ) continue;

            const fichier = await recupererFichier(id);

            if (fichier) {

                const transfert = new DataTransfer();

                transfert.items.add(fichier);

                champ.files = transfert.files;

                // Actualiser le lien Voir et les autres écouteurs existants
                champ.dispatchEvent(
                    new Event("change", { bubbles: true })
                );
            }

            // Enregistrer les nouveaux choix
            champ.addEventListener("change", async () => {

                const nouveauFichier = champ.files[0];

                if (nouveauFichier) {
                    await enregistrerFichier(id, nouveauFichier);
                } else {
                    await supprimerFichier(id);
                }

            });

        }

    } catch (erreur) {

        console.error(
            "Erreur lors de la restauration des documents :",
            erreur
        );

        alert(
            "Impossible de restaurer certains documents. Veuillez vérifier votre navigateur."
        );

    } finally {

        if (bouton) bouton.disabled = false;

    }

});



// Si l'utilisateur démarre un tout nouveau dossier sur l'étape 1 (personnel.html)
// sans formulaire en cours dans sessionStorage, on nettoie aussi IndexedDB
// pour qu'aucun ancien fichier ne réapparaisse à l'étape des documents.
document.addEventListener("DOMContentLoaded", async () => {
    if (document.body.dataset.page === "personnel") {
        const aDesDonneesEnCours = Object.keys(sessionStorage).some(cle =>
            cle.startsWith("depotMemoire_formulaire_")
        );
        if (!aDesDonneesEnCours) {
            await viderFichiersIndexedDB();
        }
    }
});
