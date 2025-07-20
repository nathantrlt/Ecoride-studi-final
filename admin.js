// js/admin.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("admin.js chargé.");
  // Dans votre admin.js, après le chargement du DOM

  // Dans votre admin.js, après le chargement du DOM

  // Référence à l'élément déroulant (le div avec la classe collapse)
  const createEmployeeCollapseElement = document.getElementById(
    "createEmployeeCollapse"
  );
  // Référence à l'élément qui déclenche le déroulement (le div cliquable avec data-bs-toggle)
  // On peut le trouver en cherchant l'élément qui cible #createEmployeeCollapse
  const createEmployeeToggler = document.querySelector(
    `[data-bs-target="#${createEmployeeCollapseElement.id}"]`
  );

  if (createEmployeeCollapseElement && createEmployeeToggler) {
    // Référence à l'icône à l'intérieur de l'élément déclencheur
    const collapseIcon = createEmployeeToggler.querySelector(".collapse-icon");

    if (collapseIcon) {
      // S'assurer que l'icône est trouvée
      createEmployeeCollapseElement.addEventListener("show.bs.collapse", () => {
        // Lorsque la section s'ouvre
        // Utiliser le caractère flèche vers le haut ▲
        collapseIcon.textContent = "▲";
      });

      createEmployeeCollapseElement.addEventListener("hide.bs.collapse", () => {
        // Lorsque la section se ferme
        // Utiliser le caractère flèche vers le bas ▼
        collapseIcon.textContent = "▼";
      });
    } else {
      console.warn(
        "Icône de déroulement (élément avec classe .collapse-icon) non trouvée."
      );
    }
  } else {
    console.warn(
      "Élément de déroulement (#createEmployeeCollapse) ou son déclencheur non trouvés."
    );
  }

  // --- Références aux éléments DOM ---
  const adminMessageDiv = document.getElementById("admin-message"); // Messages généraux
  const createEmployeeForm = document.getElementById("create-employee-form"); // Formulaire création employé
  const createEmployeeMessageDiv = document.getElementById(
    "create-employee-message"
  ); // Messages création employé
  const ridesPerDayChartCanvas = document.getElementById("ridesPerDayChart"); // Canvas pour graphique covoiturages
  const creditsPerDayChartCanvas =
    document.getElementById("creditsPerDayChart"); // Canvas pour graphique crédits
  const totalCreditsValueElement = document.getElementById(
    "total-credits-value"
  ); // Élément pour total crédits
  const accountSearchInput = document.getElementById("account-search"); // Champ recherche comptes
  const accountsListDiv = document.getElementById("accounts-list"); // Liste des comptes
  const manageAccountsMessageDiv = document.getElementById(
    "manage-accounts-message"
  ); // Messages gestion comptes

  // --- Fonction pour afficher un message général ---
  function displayAdminMessage(message, type = "info") {
    if (adminMessageDiv) {
      adminMessageDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
      // Masquer le message après quelques secondes (optionnel)
      // setTimeout(() => { adminMessageDiv.innerHTML = ''; }, 5000);
    }
  }

  // --- Fonction pour afficher un message spécifique à la création d'employé ---
  function displayCreateEmployeeMessage(message, type = "info") {
    if (createEmployeeMessageDiv) {
      createEmployeeMessageDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
      // setTimeout(() => { createEmployeeMessageDiv.innerHTML = ''; }, 5000);
    }
  }

  // --- Fonction pour afficher un message spécifique à la gestion des comptes ---
  function displayManageAccountsMessage(message, type = "info") {
    if (manageAccountsMessageDiv) {
      manageAccountsMessageDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
      // setTimeout(() => { manageAccountsMessageDiv.innerHTML = ''; }, 5000);
    }
  }

  // --- Vérification d'autorisation de l'Administrateur ---
  async function checkAdminStatusAndLoadData() {
    console.log("Vérification du statut Administrateur...");
    try {
      const response = await fetch("check_admin_status.php");
      const data = await response.json();

      if (response.ok && data.is_admin) {
        console.log(
          "Utilisateur est Administrateur. Chargement des données de l'espace Admin..."
        );
        // L'utilisateur est Administrateur, charger toutes les données et initialiser les sections
        loadAdminDashboardData();
      } else if (response.status === 401) {
        console.warn(
          "Utilisateur non connecté. Redirection vers la page de connexion."
        );
        displayAdminMessage(
          "Vous devez être connecté pour accéder à l'espace Administrateur.",
          "warning"
        );
        setTimeout(() => {
          window.location.href = "connexion.html";
        }, 2000);
      } else {
        console.warn(
          "Utilisateur n'est pas Administrateur. Redirection vers la page d'accueil."
        );
        displayAdminMessage(
          "Accès refusé. Vous n'avez pas les permissions d'administrateur.",
          "danger"
        );
        setTimeout(() => {
          window.location.href = "index.html";
        }, 2000);
      }
    } catch (error) {
      console.error(
        "Erreur lors de la vérification du statut Administrateur:",
        error
      );
      displayAdminMessage(
        "Une erreur est survenue lors de la vérification de vos permissions. Redirection...",
        "danger"
      );
      setTimeout(() => {
        window.location.href = "index.html";
      }, 3000); // Rediriger même en cas d'erreur réseau grave
    }
  }

  // --- Fonction principale pour charger toutes les données de l'espace Admin ---
  function loadAdminDashboardData() {
    console.log("Chargement des données du tableau de bord Admin...");

    // Charger les données pour les graphiques
    loadChartData();

    // Charger le total des crédits
    loadTotalCredits();

    // Charger la liste des comptes
    loadAccounts();

    // Ajouter les écouteurs d'événements pour les formulaires/actions
    addEventListeners(); // Fonction à définir
  }

  // --- Fonctions pour charger les données spécifiques (à implémenter) ---

  async function loadChartData() {
    console.log("Chargement des données pour les graphiques...");
    // Appeler un script backend pour obtenir les données des graphiques (covoiturages par jour, crédits par jour)
    // backend/get_chart_data.php (à créer)
    try {
      const response = await fetch("get_chart_data.php"); // CRÉER CE SCRIPT
      const data = await response.json();

      if (response.ok) {
        console.log("Données graphiques reçues:", data);
        // Initialiser et dessiner les graphiques avec les données reçues
        if (data.rides_per_day && data.credits_per_day) {
          drawCharts(data.rides_per_day, data.credits_per_day);
        } else {
          console.warn(
            "Données graphiques incomplètes ou manquantes dans la réponse."
          );
          // Afficher un message dans les sections graphiques si les données sont manquantes
        }
      } else {
        console.error(
          "Erreur lors du chargement des données graphiques:",
          data.error
        );
        displayAdminMessage(
          "Erreur lors du chargement des données graphiques: " +
            (data.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error(
        "Erreur fetch lors du chargement des données graphiques:",
        error
      );
      displayAdminMessage(
        "Erreur de communication lors du chargement des données graphiques.",
        "danger"
      );
    }
  }

  async function loadTotalCredits() {
    console.log("Chargement du total des crédits...");
    // Appeler un script backend pour obtenir le total des crédits de la plateforme
    // backend/get_total_credits.php (à créer)
    if (totalCreditsValueElement) {
      totalCreditsValueElement.textContent = "Chargement..."; // Message de chargement
    }
    try {
      const response = await fetch("get_total_credits.php"); // CRÉER CE SCRIPT
      const data = await response.json();

      if (response.ok && data.total_credits !== undefined) {
        console.log("Total crédits reçu:", data.total_credits);
        if (totalCreditsValueElement) {
          totalCreditsValueElement.textContent = data.total_credits; // Afficher le total
        }
      } else {
        console.error(
          "Erreur lors du chargement du total des crédits:",
          data.error
        );
        if (totalCreditsValueElement) {
          totalCreditsValueElement.textContent = "Erreur"; // Afficher une erreur
        }
        displayAdminMessage(
          "Erreur lors du chargement du total des crédits: " +
            (data.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error(
        "Erreur fetch lors du chargement du total des crédits:",
        error
      );
      if (totalCreditsValueElement) {
        totalCreditsValueElement.textContent = "Erreur"; // Afficher une erreur
      }
      displayAdminMessage(
        "Erreur de communication lors du chargement du total des crédits.",
        "danger"
      );
    }
  }

  async function loadAccounts() {
    console.log("Chargement de la liste des comptes...");
    // Appeler un script backend pour obtenir la liste des comptes (utilisateurs et employés)
    // backend/list_accounts.php (à créer)
    if (accountsListDiv) {
      accountsListDiv.innerHTML = "<p>Chargement des comptes...</p>"; // Message de chargement
    }
    try {
      const response = await fetch("list_accounts.php"); // CRÉER CE SCRIPT
      const data = await response.json();

      if (response.ok && Array.isArray(data)) {
        console.log("Liste des comptes reçue:", data);
        displayAccounts(data); // Fonction pour afficher la liste (à définir)
      } else {
        console.error(
          "Erreur lors du chargement de la liste des comptes:",
          data.error
        );
        if (accountsListDiv) {
          accountsListDiv.innerHTML = `<p class="text-danger">Erreur lors du chargement des comptes : ${
            data.error || "Erreur inconnue"
          }</p>`;
        }
        displayAdminMessage(
          "Erreur lors du chargement des comptes: " +
            (data.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error(
        "Erreur fetch lors du chargement de la liste des comptes:",
        error
      );
      if (accountsListDiv) {
        accountsListDiv.innerHTML =
          '<p class="text-danger">Une erreur est survenue lors du chargement des comptes.</p>';
      }
      displayAdminMessage(
        "Erreur de communication lors du chargement des comptes.",
        "danger"
      );
    }
  }

  // --- Fonctions pour les actions (à implémenter) ---

  function addEventListeners() {
    console.log("Ajout des écouteurs d'événements Admin...");

    // Écouteur pour le formulaire de création d'employé
    if (createEmployeeForm) {
      createEmployeeForm.addEventListener("submit", handleCreateEmployee); // Fonction à définir
    }

    // Écouteur pour la barre de recherche des comptes (optionnel)
    if (accountSearchInput) {
      accountSearchInput.addEventListener("input", handleAccountSearch); // Fonction à définir
    }

    // Les écouteurs pour les boutons de suspension de compte seront ajoutés dynamiquement
    // après que la liste des comptes soit chargée et affichée par displayAccounts().
  }

  // --- Fonctions d'affichage (à implémenter) ---

  // Fonction pour dessiner les graphiques (utilisant Chart.js)
  function drawCharts(ridesData, creditsData) {
    console.log("Dessin des graphiques...");
    // Assurez-vous que les éléments canvas existent
    if (!ridesPerDayChartCanvas || !creditsPerDayChartCanvas) {
      console.error("Éléments canvas pour graphiques non trouvés.");
      return;
    }

    // --- Graphique Covoiturages par jour ---
    const ridesCtx = ridesPerDayChartCanvas.getContext("2d");
    // Détruire l'ancien graphique si il existe pour éviter les doublons
    if (ridesPerDayChartCanvas.chart) {
      ridesPerDayChartCanvas.chart.destroy();
    }
    ridesPerDayChartCanvas.chart = new Chart(ridesCtx, {
      type: "bar", // Ou 'line'
      data: {
        labels: ridesData.labels, // Dates ou jours
        datasets: [
          {
            label: "Nombre de covoiturages",
            data: ridesData.values, // Nombre de covoiturages
            backgroundColor: "rgba(75, 192, 192, 0.6)",
            borderColor: "rgba(75, 192, 192, 1)",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Nombre",
            },
          },
          x: {
            title: {
              display: true,
              text: "Jour",
            },
          },
        },
        plugins: {
          legend: {
            display: true,
          },
        },
      },
    });

    // --- Graphique Gain de crédits par jour ---
    const creditsCtx = creditsPerDayChartCanvas.getContext("2d");
    // Détruire l'ancien graphique si il existe
    if (creditsPerDayChartCanvas.chart) {
      creditsPerDayChartCanvas.chart.destroy();
    }
    creditsPerDayChartCanvas.chart = new Chart(creditsCtx, {
      type: "line", // Ou 'bar'
      data: {
        labels: creditsData.labels, // Dates ou jours
        datasets: [
          {
            label: "Gain de crédits",
            data: creditsData.values, // Crédits gagnés
            backgroundColor: "rgba(153, 102, 255, 0.6)",
            borderColor: "rgba(153, 102, 255, 1)",
            borderWidth: 1,
            fill: false, // Ne pas remplir sous la ligne pour le graphique linéaire
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Crédits",
            },
          },
          x: {
            title: {
              display: true,
              text: "Jour",
            },
          },
        },
        plugins: {
          legend: {
            display: true,
          },
        },
      },
    });
  }

  // Fonction pour afficher la liste des comptes
  // Fonction pour afficher la liste des comptes
  // Fonction pour afficher la liste des comptes
  function displayAccounts(accounts) {
    console.log("Affichage de la liste des comptes...", accounts);
    if (!accountsListDiv) {
      console.error("Élément accountsListDiv non trouvé.");
      return;
    }

    accountsListDiv.innerHTML = ""; // Vider la liste existante

    if (accounts.length === 0) {
      accountsListDiv.innerHTML = "<p>Aucun compte trouvé.</p>";
      return;
    }

    let accountsHtml = '<ul class="list-group">';

    accounts.forEach((account) => {
      // Assurez-vous que les noms de propriétés (account.quelque_chose)
      // correspondent à ce que backend/list_accounts.php renvoie.
      // Nous nous attendons maintenant à: utilisateur_id, pseudo, email, nom, prenom, statut_compte
      const accountStatus = account.statut_compte ?? "Inconnu"; // Utilisez statut_compte
      // Déterminer le texte et la classe du bouton en fonction du statut
      const suspendButtonText =
        accountStatus === "Suspendu" || accountStatus === "suspendu"
          ? "Réactiver"
          : "Suspendre";
      const suspendButtonClass =
        accountStatus === "Suspendu" || accountStatus === "suspendu"
          ? "btn-warning"
          : "btn-danger"; // Couleur différente si déjà suspendu

      accountsHtml += `
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                          <strong>${account.prenom ?? "N/A"} ${
        account.nom ?? "N/A"
      }</strong> (${
        account.pseudo ?? "N/A"
      }) <br> <!-- Afficher Prénom Nom (Pseudo) -->
                          <small>${account.email ?? "N/A"}</small> <br>
                           <small>Statut: ${accountStatus}</small> <!-- Afficher le statut du compte -->
                      </div>
                      <div class="account-actions">
                          <!-- Ajouter le bouton Suspendre/Réactiver -->
                          <!-- data-account-id pour l'ID de l'utilisateur, data-current-status pour le statut actuel -->
                          <button class="btn btn-sm ${suspendButtonClass} suspend-account-btn"
                                  data-account-id="${account.utilisateur_id}"
                                   data-current-status="${accountStatus}">
                               ${suspendButtonText}
                          </button>
                      </div>
                  </li>
              `;
    });

    accountsHtml += "</ul>";
    accountsListDiv.innerHTML = accountsHtml;

    // Ajouter les écouteurs d'événements aux nouveaux boutons Suspendre/Réactiver
    addAccountButtonListeners(); // Appeler la fonction pour ajouter les écouteurs
  }

  // addAccountButtonListeners reste inchangée car elle utilise data-account-id et data-current-status
  // handleSuspendAccount reste inchangée car elle prend accountId et newStatus

  // addAccountButtonListeners reste inchangée car elle utilise data-account-id et data-current-status
  // handleSuspendAccount reste inchangée car elle prend accountId et newStatus

  function addAccountButtonListeners() {
    console.log("Ajout des écouteurs pour les boutons de gestion de compte.");
    // Supprimer les écouteurs existants pour éviter les doublons
    document.querySelectorAll(".suspend-account-btn").forEach((button) => {
      // Cloner et remplacer l'élément pour supprimer tous les écouteurs attachés
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);
    });

    document.querySelectorAll(".suspend-account-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const accountId = event.target.dataset.accountId;
        const currentStatus = event.target.dataset.currentStatus;
        // Déterminer le nouveau statut basé sur le statut actuel
        const newStatus =
          currentStatus === "Suspendu" || currentStatus === "suspendu"
            ? "Actif"
            : "Suspendu";
        const actionText =
          currentStatus === "Suspendu" || currentStatus === "suspendu"
            ? "réactiver"
            : "suspendre";

        console.log(
          `${actionText} le compte avec ID: ${accountId}, statut actuel: ${currentStatus}`
        );

        // Confirmation avant l'action (optionnel mais recommandé)
        if (confirm(`Confirmez-vous vouloir ${actionText} ce compte ?`)) {
          // Appeler la fonction pour gérer la suspension/réactivation via le backend
          await handleSuspendAccount(accountId, newStatus, actionText);
        }
      });
    });
  }

  // --- Fonctions de gestion des actions (à implémenter) ---

  // Gère la soumission du formulaire de création d'employé
  // Gère la soumission du formulaire de création d'employé
  async function handleCreateEmployee(event) {
    event.preventDefault();
    console.log("Formulaire de création d'employé soumis.");
    // Vider les messages précédents
    displayCreateEmployeeMessage("", "info");

    const pseudoInput = document.getElementById("employee-pseudo");
    const emailInput = document.getElementById("employee-email");
    const passwordInput = document.getElementById("employee-password");
    // --- AJOUT DES RÉFÉRENCES AUX NOUVEAUX CHAMPS ---
    const nomInput = document.getElementById("employee-nom");
    const prenomInput = document.getElementById("employee-prenom");
    const telephoneInput = document.getElementById("employee-telephone");
    const adresseInput = document.getElementById("employee-adresse");
    const dateNaissanceInput = document.getElementById(
      "employee-date-naissance"
    );
    // --- FIN AJOUT ---

    const employeeData = {
      pseudo: pseudoInput ? pseudoInput.value.trim() : "",
      email: emailInput ? emailInput.value.trim() : "",
      password: passwordInput ? passwordInput.value : "",
      // --- AJOUT DES DONNÉES DES NOUVEAUX CHAMPS ---
      nom: nomInput ? nomInput.value.trim() : "",
      prenom: prenomInput ? prenomInput.value.trim() : "",
      telephone: telephoneInput ? telephoneInput.value.trim() : "",
      adresse: adresseInput ? adresseInput.value.trim() : "",
      date_naissance: dateNaissanceInput ? dateNaissanceInput.value : "", // La valeur d'un input type="date" est au format YYYY-MM-DD
      // --- FIN AJOUT ---
    };

    // Validation basique (vous pouvez ajouter plus de validations si nécessaire)
    if (
      !employeeData.pseudo ||
      !employeeData.email ||
      !employeeData.password ||
      !employeeData.nom ||
      !employeeData.prenom // Ajoutez d'autres champs si ils sont requis
    ) {
      displayCreateEmployeeMessage(
        "Veuillez remplir tous les champs obligatoires.",
        "warning"
      );
      return;
    }

    // Désactiver le bouton de soumission pendant le traitement
    const submitButton = createEmployeeForm.querySelector(
      'button[type="submit"]'
    );
    if (submitButton) submitButton.disabled = true;

    try {
      // Appeler un script backend pour créer l'employé
      // backend/create_employee.php
      const response = await fetch("create_employee.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(employeeData), // employeeData inclut maintenant les nouveaux champs
      });
      const result = await response.json();

      // Réactiver le bouton de soumission
      if (submitButton) submitButton.disabled = false;

      if (response.ok) {
        displayCreateEmployeeMessage(result.success, "success");
        // Réinitialiser le formulaire après succès
        createEmployeeForm.reset();
        // Optionnel : Recharger la liste des comptes si elle est affichée (pour voir le nouvel employé)
        // loadAccounts(); // Appeler si vous voulez rafraîchir la liste après création
      } else {
        // Afficher l'erreur du backend
        displayCreateEmployeeMessage(
          "Erreur lors de la création de l'employé : " +
            (result.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error("Erreur lors de la création de l'employé:", error);
      // Réactiver le bouton de soumission en cas d'erreur fetch
      if (submitButton) submitButton.disabled = false;
      displayCreateEmployeeMessage(
        "Une erreur de communication est survenue lors de la création de l'employé.",
        "danger"
      );
    }
  }

  // Gère la recherche de comptes (optionnel)
  function handleAccountSearch() {
    console.log("Recherche de compte:", accountSearchInput.value);
    // Cette fonction pourrait filtrer la liste affichée côté client
    // ou déclencher un nouvel appel backend avec le terme de recherche.
    // Pour l'instant, nous allons juste logger. L'implémentation dépend de la complexité souhaitée.
  }

  // Gère l'ajout des écouteurs pour les boutons Suspendre/Réactiver
  function addAccountButtonListeners() {
    console.log("Ajout des écouteurs pour les boutons de gestion de compte.");
    // Supprimer les écouteurs existants pour éviter les doublons
    document.querySelectorAll(".suspend-account-btn").forEach((button) => {
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);
    });

    document.querySelectorAll(".suspend-account-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const accountId = event.target.dataset.accountId;
        const currentStatus = event.target.dataset.currentStatus;
        const newStatus = currentStatus === "Suspendu" ? "Actif" : "Suspendu";
        const actionText =
          currentStatus === "Suspendu" ? "réactiver" : "suspendre";

        console.log(
          `${actionText} le compte avec ID: ${accountId}, statut actuel: ${currentStatus}`
        );

        if (confirm(`Confirmez-vous vouloir ${actionText} ce compte ?`)) {
          await handleSuspendAccount(accountId, newStatus, actionText); // Fonction à définir
        }
      });
    });
  }

  // Gère l'appel backend pour suspendre/réactiver un compte
  // Gère l'appel backend pour suspendre/réactiver un compte
  async function handleSuspendAccount(accountId, newStatus, actionText) {
    console.log(
      `Appel backend pour ${actionText} le compte ${accountId} au statut ${newStatus}.`
    );
    // Le script backend à appeler est backend/update_account_status.php

    // Désactiver le bouton cliqué pendant le traitement pour éviter les clics multiples
    const button = document.querySelector(
      `.suspend-account-btn[data-account-id="${accountId}"]`
    );
    if (button) button.disabled = true;
    // Optionnel : changer le texte ou l'apparence du bouton pour indiquer qu'il est en cours

    try {
      // Envoyer la requête POST au script backend
      const response = await fetch("update_account_status.php", {
        // Assurez-vous que le chemin est correct
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: accountId, // Utiliser user_id pour correspondre à ce que le backend attend
          statut_compte: newStatus, // Le nouveau statut ('Actif' ou 'Suspendu')
        }),
      });
      const result = await response.json(); // Parser la réponse JSON

      // Réactiver le bouton après traitement (réussite ou échec)
      if (button) button.disabled = false;
      // Optionnel : rétablir le texte/l'apparence si vous les avez changés

      if (response.ok) {
        // Action réussie
        displayManageAccountsMessage(result.success, "success");
        console.log(`${actionText} du compte ${accountId} réussi.`);
        // Recharger la liste des comptes pour refléter le changement de statut
        loadAccounts(); // Appeler loadAccounts pour rafraîchir l'affichage
      } else {
        // Afficher l'erreur du backend
        console.error(
          `Erreur backend lors de la ${actionText} du compte ${accountId}:`,
          result.error
        );
        displayManageAccountsMessage(
          `Erreur lors de la ${actionText} du compte : ${
            result.error || "Erreur inconnue"
          }`,
          "danger"
        );
      }
    } catch (error) {
      // Gérer les erreurs de réseau ou de fetch
      console.error(`Erreur lors de la ${actionText} du compte:`, error);
      // Réactiver le bouton en cas d'erreur fetch
      if (button) button.disabled = false;
      displayManageAccountsMessage(
        `Une erreur de communication est survenue lors de la ${actionText} du compte.`,
        "danger"
      );
    }
  }

  // --- Exécution au chargement de la page ---
  checkAdminStatusAndLoadData(); // Lancer la vérification et le chargement des données
}); // Fin de DOMContentLoaded
