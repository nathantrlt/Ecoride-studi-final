// js/auth.js

document.addEventListener("DOMContentLoaded", () => {
  // --- Références aux éléments de navigation ---
  // Ces variables peuvent être null si les éléments ne sont pas sur la page.
  const linkConnexion = document.getElementById("link-connexion");
  const linkInscription = document.getElementById("link-inscription");
  const linkDeconnexionLi = document.getElementById("link-deconnexion");
  const linkEspace = document.getElementById("link-espace");
  const linkHistorique = document.getElementById("link-historique");
  // --- Nouvelle référence pour le lien Espace Employé ---
  const linkEspaceEmploye = document.getElementById("link-espace-employe");
  // --- Nouvelle référence pour le lien Espace Administrateur (AJOUTÉ) ---
  const linkEspaceAdmin = document.getElementById("link-espace-administrateur");

  // Masquer les liens sensibles par défaut
  if (linkDeconnexionLi) linkDeconnexionLi.style.display = "none";
  if (linkEspace && linkEspace.parentElement)
    linkEspace.parentElement.style.display = "none";
  if (linkHistorique && linkHistorique.parentElement)
    linkHistorique.parentElement.style.display = "none";
  if (linkEspaceEmploye) {
    linkEspaceEmploye.style.display = "none";
  }
  // --- Masquer le lien Espace Administrateur par défaut (AJOUTÉ) ---
  if (linkEspaceAdmin) {
    linkEspaceAdmin.style.display = "none";
  }

  // --- Fonction pour vérifier l'état de connexion et les rôles via le backend ---
  async function checkConnectionAndRoleStatus() {
    try {
      // Appel initial pour vérifier l'état de connexion
      const connectionResponse = await fetch("check_connexion.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" },
      });
      const connectionData = await connectionResponse.json();

      if (
        connectionResponse.ok &&
        typeof connectionData.is_connected !== "undefined"
      ) {
        if (connectionData.is_connected) {
          if (linkConnexion && linkConnexion.parentElement)
            linkConnexion.parentElement.style.display = "none";
          if (linkInscription && linkInscription.parentElement)
            linkInscription.parentElement.style.display = "none";
          if (linkDeconnexionLi) linkDeconnexionLi.style.display = "list-item";
          if (linkEspace && linkEspace.parentElement)
            linkEspace.parentElement.style.display = "list-item";
          if (linkHistorique && linkHistorique.parentElement)
            linkHistorique.parentElement.style.display = "list-item";

          try {
            // Utilisation d'un try-catch interne pour ne pas bloquer le reste
            const roleEmployeeResponse = await fetch(
              "check_employe_status.php",
              {
                // Appel au script Employé
                method: "GET",
                headers: { "Content-Type": "application/json" },
              }
            );
            const roleEmployeeData = await roleEmployeeResponse.json();

            if (
              roleEmployeeResponse.ok &&
              typeof roleEmployeeData.is_employe !== "undefined"
            ) {
              if (roleEmployeeData.is_employe) {
                if (linkEspaceEmploye) {
                  linkEspaceEmploye.style.display = "block"; // Afficher l'élément <li>
                }
              } else {
                if (linkEspaceEmploye) {
                  linkEspaceEmploye.style.display = "none";
                }
              }
            } else {
              console.error(
                "auth.js: Réponse inattendue de check_employe_status.php",
                roleEmployeeData
              );
              // En cas d'erreur, masquer le lien Espace Employé par sécurité
              if (linkEspaceEmploye) {
                linkEspaceEmploye.style.display = "none";
              }
            }
          } catch (error) {
            console.error(
              "auth.js: Erreur lors de l'appel à check_employe_status.php:",
              error
            );
            if (linkEspaceEmploye) {
              linkEspaceEmploye.style.display = "none";
            }
          }

          try {
            const roleAdminResponse = await fetch(
              "check_admin_status.php",
              {
                method: "GET",
                headers: { "Content-Type": "application/json" },
              }
            );
            const roleAdminData = await roleAdminResponse.json();

            if (
              roleAdminResponse.ok &&
              typeof roleAdminData.is_admin !== "undefined"
            ) {
              if (roleAdminData.is_admin) {
                if (linkEspaceAdmin) {
                  linkEspaceAdmin.style.display = "block";
                }
              } else {
                if (linkEspaceAdmin) {
                  linkEspaceAdmin.style.display = "none";
                }
              }
            } else {
              console.error(
                "auth.js: Réponse inattendue de check_admin_status.php",
                roleAdminData
              );
              if (linkEspaceAdmin) {
                linkEspaceAdmin.style.display = "none";
              }
            }
          } catch (error) {
            console.error(
              "auth.js: Erreur lors de l'appel à check_admin_status.php:",
              error
            );
            if (linkEspaceAdmin) {
              linkEspaceAdmin.style.display = "none";
            }
          }

          // Afficher le lien de déconnexion
          if (document.getElementById("link-deconnexion"))
            document.getElementById("link-deconnexion").style.display =
              "list-item";
        } else {
          // Utilisateur non connecté - Masquer les liens basiques et spécifiques
          if (linkConnexion && linkConnexion.parentElement)
            linkConnexion.parentElement.style.display = "list-item";
          if (linkInscription && linkInscription.parentElement)
            linkInscription.parentElement.style.display = "list-item";
          if (linkDeconnexionLi) linkDeconnexionLi.style.display = "none";
          if (linkEspace && linkEspace.parentElement)
            linkEspace.parentElement.style.display = "none";
          if (linkHistorique && linkHistorique.parentElement)
            linkHistorique.parentElement.style.display = "none";
          if (linkEspaceEmploye) {
            linkEspaceEmploye.style.display = "none";
          }
          // --- Masquer le lien Espace Administrateur si l'utilisateur n'est pas connecté (AJOUTÉ) ---
          if (linkEspaceAdmin) {
            linkEspaceAdmin.style.display = "none";
          }
        }
      } else {
        console.error(
          "auth.js: Réponse inattendue de check_connexion.php",
          connectionData
        );
        // En cas d'erreur, masquer les liens sensibles par sécurité
        if (linkEspaceEmploye) linkEspaceEmploye.style.display = "none";
        if (linkEspaceAdmin) linkEspaceAdmin.style.display = "none"; // AJOUTÉ
        // Et s'assurer que les liens connexion/inscription sont visibles
        if (document.getElementById("link-connexion"))
          document.getElementById("link-connexion").style.display = "list-item";
        if (document.getElementById("link-inscription"))
          document.getElementById("link-inscription").style.display =
            "list-item";
        if (document.getElementById("link-espace"))
          document.getElementById("link-espace").style.display = "none";
        if (document.getElementById("link-historique"))
          document.getElementById("link-historique").style.display = "none";
        if (document.getElementById("link-deconnexion"))
          document.getElementById("link-deconnexion").style.display = "none";
      }
    } catch (error) {
      // En cas d'erreur fetch, masquer les liens sensibles
      const linkEspaceEmploye = document.getElementById("link-espace-employe");
      const linkEspaceAdmin = document.getElementById(
        "link-espace-administrateur"
      ); // AJOUTÉ
      if (linkEspaceEmploye) linkEspaceEmploye.style.display = "none";
      if (linkEspaceAdmin) linkEspaceAdmin.style.display = "none"; // AJOUTÉ
      // Et s'assurer que les liens connexion/inscription sont visibles
      if (document.getElementById("link-connexion"))
        document.getElementById("link-connexion").style.display = "list-item";
      if (document.getElementById("link-inscription"))
        document.getElementById("link-inscription").style.display = "list-item";
      if (document.getElementById("link-espace"))
        document.getElementById("link-espace").style.display = "none";
      if (document.getElementById("link-historique"))
        document.getElementById("link-historique").style.display = "none";
      if (document.getElementById("link-deconnexion"))
        document.getElementById("link-deconnexion").style.display = "none";
    }
  }

  checkConnectionAndRoleStatus();

  const linkDeconnexion = linkDeconnexionLi
    ? linkDeconnexionLi.querySelector("a")
    : null;

  if (linkDeconnexion) {
    linkDeconnexion.addEventListener("click", async (event) => {
      event.preventDefault();

      try {
        const response = await fetch("deconnexion.php", {
          method: "GET",
        });

        if (response.ok) {
          window.location.href = "index.html";
        } else {
          console.error(
            "auth.js: Erreur HTTP lors de l'appel à deconnexion.php",
            response.status
          );
          alert("Une erreur est survenue lors de la déconnexion.");
        }
      } catch (error) {
        console.error(
          "auth.js: Erreur lors de l'appel à deconnexion.php:",
          error
        );
        alert(
          "Une erreur de communication est survenue lors de la déconnexion."
        );
      }
    });
  } else {
    console.warn(
      "auth.js: Le lien de déconnexion <a> n'a pas pu être trouvé (#link-deconnexion manquant ou vide)."
    );
  }
}); // Fin de l'écouteur DOMContentLoaded
