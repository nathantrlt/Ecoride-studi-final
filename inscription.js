document.addEventListener("DOMContentLoaded", () => {
  console.log("inscription.js chargé.");

  const signupForm = document.getElementById("signup-form");
  const signupMessageDiv = document.getElementById("signup-message");

  if (signupForm && signupMessageDiv) {
    console.log("Formulaire d'inscription et div de message trouvés.");

    // Ajouter un écouteur d'événement pour la soumission du formulaire
    signupForm.addEventListener("submit", (event) => {
      event.preventDefault(); // Empêche la soumission par défaut

      console.log("Formulaire d'inscription soumis.");

      const pseudo = document.getElementById("pseudo").value.trim();
      const email = document.getElementById("email").value.trim();
      const password = document.getElementById("password").value;
      const confirmPassword = document.getElementById("confirm-password").value;
      const nomInput = document.getElementById("nom");
      const prenomInput = document.getElementById("prenom");
      const telephoneInput = document.getElementById("telephone");
      const adresseInput = document.getElementById("adresse");
      const dateNaissanceInput = document.getElementById("date-naissance");

      if (password !== confirmPassword) {
        signupMessageDiv.innerHTML =
          "<p class='text-danger'>Les mots de passe ne correspondent pas.</p>";
        return; // Arrêter l'exécution si les mots de passe ne correspondent pas
      }

      if (
        !nomInput ||
        !nomInput.value.trim() ||
        !prenomInput ||
        !prenomInput.value.trim()
      ) {
        signupMessageDiv.innerHTML =
          "<p class='text-danger'>Veuillez remplir les champs Nom et Prénom.</p>";
        return;
      }

      // Préparer les données à envoyer au backend
      const signupData = {
        pseudo: pseudo,
        email: email,
        password: password,
        // --- AJOUT DES DONNÉES DES NOUVEAUX CHAMPS ---
        nom: nomInput.value.trim(),
        prenom: prenomInput.value.trim(),
        telephone: telephoneInput ? telephoneInput.value.trim() : "", // Gérer si le champ est optionnel ou absent
        adresse: adresseInput ? adresseInput.value.trim() : "",
        date_naissance: dateNaissanceInput ? dateNaissanceInput.value : "", // La valeur d'un input type="date" est au format YYYY-MM-DD
      };

      fetch("inscription.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(signupData),
      })
        .then((response) => {
          console.log("Réponse reçue du backend (inscription).");
          return response.json(); // Toujours essayer de parser la réponse comme JSON
        })
        .then((data) => {
          if (data.error) {
            // Afficher les erreurs renvoyées par le backend
            console.error("Erreur du backend:", data.error);
            signupMessageDiv.innerHTML = `<p class='text-danger'>Erreur d'inscription : ${data.error}</p>`;
          } else if (data.success) {
            // Afficher le message de succès du backend
            signupMessageDiv.innerHTML = `<p class='text-success'>${data.success}</p>`;
            // Optionnel : Réinitialiser le formulaire ou rediriger l'utilisateur
            signupForm.reset(); // Réinitialiser le formulaire après inscription réussie
            // window.location.href = 'connexion.html'; // Rediriger vers la page de connexion
          } else {
            // Gérer d'autres types de réponses inattendues
            signupMessageDiv.innerHTML = `<p class='text-warning'>Réponse inattendue du serveur.</p>`;
          }
        })
        .catch((error) => {
          // Gérer les erreurs de réseau ou de fetch
          console.error(
            "Erreur lors de l'envoi de la requête ou du traitement de la réponse:",
            error
          );
          signupMessageDiv.innerHTML =
            '<p class="text-danger">Une erreur est survenue lors de la communication avec le serveur.</p>';
        });
    });
  } else {
    console.error(
      "Éléments formulaire d'inscription ou div de message non trouvés."
    );
  }
});
