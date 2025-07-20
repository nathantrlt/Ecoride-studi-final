// js/connexion.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("connexion.js chargé.");

  const loginForm = document.getElementById("login-form");
  const loginMessageDiv = document.getElementById("login-message");

  if (loginForm && loginMessageDiv) {
    console.log("Formulaire de connexion et div de message trouvés.");

    // Ajouter un écouteur d'événement pour la soumission du formulaire
    loginForm.addEventListener("submit", (event) => {
      event.preventDefault(); // Empêche la soumission par défaut

      console.log("Formulaire de connexion soumis.");

      // Récupérer les valeurs des champs du formulaire
      const emailOrPseudo = document
        .getElementById("email-or-pseudo")
        .value.trim();
      const password = document.getElementById("login-password").value; // Ne pas trimmer le mot de passe

      // Validation côté client (optionnel mais recommandé)
      if (empty(emailOrPseudo) || empty(password)) {
        loginMessageDiv.innerHTML =
          "<p class='text-danger'>Veuillez remplir tous les champs.</p>";
        return;
      }
      // Vous pourriez ajouter ici d'autres validations (format email si l'utilisateur entre un email)

      // Préparer les données à envoyer au backend
      const loginData = {
        identifier: emailOrPseudo, // Nous utilisons 'identifier' pour couvrir email ou pseudo
        password: password,
      };
      // Envoyer les données au script PHP backend en utilisant fetch
      // Nous allons créer un script backend nommé 'login.php' ou 'connexion_process.php'
      // Assurez-vous que le chemin est correct
      fetch("connexion.php", {
        // ou '/ecoride/backend/connexion_process.php'
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(loginData),
      })
        .then((response) => {
          console.log("Réponse reçue du backend (connexion).");
          // Ne pas vérifier !response.ok ici, car même les messages d'erreur du backend pourraient avoir un statut 200 pour un JSON valide
          return response.json(); // Toujours essayer de parser la réponse comme JSON
        })
        .then((data) => {
          if (data.error) {
            // Afficher les erreurs renvoyées par le backend
            console.error("Erreur du backend:", data.error);
            loginMessageDiv.innerHTML = `<p class='text-danger'>Erreur de connexion : ${data.error}</p>`;
          } else if (data.success) {
            // Afficher le message de succès et gérer la session
            loginMessageDiv.innerHTML = `<p class='text-success'>${data.success}</p>`;
            console.log("Connexion réussie ! Redirection...");

            // --- REDIRECTION CONDITIONNELLE BASÉE SUR LE RÔLE D'EMPLOYÉ ---
            if (data.is_employe) {
              console.log(
                "Utilisateur est un employé. Redirection vers l'espace employé."
              );
              window.location.href = "espace_employe.html"; // Redirection vers l'espace employé
            } else {
              console.log(
                "Utilisateur n'est pas un employé. Redirection vers la page d'accueil."
              );
              window.location.href = "index.html"; // Redirection vers la page d'accueil ou l'espace utilisateur par défaut
            }
            // --- FIN DE LA REDIRECTION CONDITIONNELLE ---
          } else {
            // Gérer d'autres types de réponses inattendues
            loginMessageDiv.innerHTML = `<p class='text-warning'>Réponse inattendue du serveur.</p>`;
          }
        })
        .catch((error) => {
          // Gérer les erreurs de réseau ou de fetch
          console.error(
            "Erreur lors de l'envoi de la requête ou du traitement de la réponse:",
            error
          );
          loginMessageDiv.innerHTML =
            '<p class="text-danger">Une erreur est survenue lors de la communication avec le serveur.</p>';
        });
    });
  } else {
    console.error(
      "Éléments formulaire de connexion ou div de message non trouvés."
    );
  }

  // Fonction utilitaire simple pour vérifier si une chaîne est vide ou composée d'espaces blancs
  function empty(str) {
    return !str || str.trim().length === 0;
  }
});
