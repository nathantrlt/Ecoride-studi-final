// js/script.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM entièrement chargé."); // Pour vérifier si le script s'exécute

  const searchRideForm = document.getElementById("search-ride-form");
  const rideResultsDiv = document.getElementById("ride-results");

  if (searchRideForm && rideResultsDiv) {
    console.log("Formulaire et div de résultats trouvés.");

    searchRideForm.addEventListener("submit", (event) => {
      event.preventDefault(); // Empêche la soumission par défaut du formulaire
      console.log("Formulaire de recherche soumis.");

      // Récupérer les valeurs du formulaire
      const departure = document.getElementById("departure-ride").value;
      const arrival = document.getElementById("arrival-ride").value;
      const date = document.getElementById("date-ride").value;

      // Récupérer les valeurs des filtres
      const filterEcological =
        document.getElementById("filter-ecological").checked; // true ou false
      const filterPriceMax = document.getElementById("filter-price-max").value; // Chaîne vide ou nombre
      const filterDurationMax = document.getElementById(
        "filter-duration-max"
      ).value; // Chaîne vide ou nombre
      const filterRatingMin =
        document.getElementById("filter-rating-min").value; // Chaîne vide ou nombre

      console.log("Données du formulaire et filtres:", {
        departure,
        arrival,
        date,
        filterEcological,
        filterPriceMax,
        filterDurationMax,
        filterRatingMin,
      });

      // Préparer les données à envoyer au backend
      const searchData = {
        departure: departure,
        arrival: arrival,
        date: date,
        filterEcological: filterEcological,
        filterPriceMax: filterPriceMax,
        filterDurationMax: filterDurationMax,
        filterRatingMin: filterRatingMin,
      };

      // Envoyer les données au script PHP backend en utilisant fetch
      // Assurez-vous que le chemin 'backend/search_rides.php' est correct par rapport à votre fichier rides.html
      fetch("search_rides.php", {
        // Notez le '/' au début et le nom du dossier projet
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(searchData),
      })
        .then((response) => {
          console.log("Réponse reçue du backend.");
          if (!response.ok) {
            // Si la réponse n'est pas OK (par exemple, erreur 404 ou 500)
            throw new Error(`Erreur HTTP! Statut: ${response.status}`);
          }
          return response.json(); // Parser la réponse JSON
        })
        .then((data) => {
          console.log("Données JSON reçues:", data);
          if (data.error) {
            // Gérer les erreurs du backend si nécessaire
            console.error("Erreur du backend:", data.error);
            rideResultsDiv.innerHTML = `<p class="text-danger">Erreur lors de la recherche : ${data.error}</p>`;
          } else {
            // Afficher les résultats reçus du backend
            displayRideResults(data);
          }
        })
        .catch((error) => {
          console.error(
            "Erreur lors de l'envoi de la requête ou du traitement de la réponse:",
            error
          );
          rideResultsDiv.innerHTML =
            '<p class="text-danger">Une erreur est survenue lors de la communication avec le serveur.</p>';
        });
    });
  } else {
    console.error(
      "Éléments formulaire de recherche ou div de résultats non trouvés."
    );
  }

  // Fonction pour afficher les résultats de covoiturage
  // Fonction pour afficher les résultats de covoiturage
  function displayRideResults(results) {
    console.log("DEBUG: displayRideResults started.", results); // Log 1

    const rideResultsDiv = document.getElementById("ride-results"); // Assurez-vous que cette div est bien récupérée ici ou plus haut

    if (!rideResultsDiv) {
      console.error("DEBUG: rideResultsDiv element not found!"); // Log si la div n'est pas trouvée
      return; // Sortir si l'élément n'existe pas
    }

    rideResultsDiv.innerHTML = ""; // Effacer les résultats précédents
    console.log("DEBUG: rideResultsDiv cleared."); // Log 2

    if (!results || results.length === 0) {
      console.log("DEBUG: No results or empty results."); // Log 3
      rideResultsDiv.innerHTML =
        "<p>Aucun covoiturage disponible pour cette recherche. Souhaitez-vous modifier votre date ?</p>";
      console.log("DEBUG: Displayed no results message."); // Log 3.1
      return; // Sortir si aucun résultat
    }

    console.log("DEBUG: Results found, starting HTML generation."); // Log 4

    let html = "<h3>Résultats de recherche :</h3>";
    console.log("DEBUG: Initial HTML string created."); // Log 5

    results.forEach((ride) => {
      console.log("DEBUG: Inside forEach loop for a ride.", ride); // Log 6

      const formattedDepartureTime = ride.departureTime
        ? ride.departureTime.substring(0, 5)
        : "N/A";
      const formattedArrivalTime = ride.arrivalTime
        ? ride.arrivalTime.substring(0, 5)
        : "N/A";

      console.log("DEBUG: Heure de départ formatée:", formattedDepartureTime); // Log formatage
      console.log("DEBUG: Heure d'arrivée formatée:", formattedArrivalTime); // Log formatage

      // Vérifier si les propriétés nécessaires existent dans l'objet ride (optionnel mais bonne pratique)
      // Vous pourriez simplifier cette vérification si vous êtes certain de la structure des données du backend
      // Pour l'instant, gardons-la.
      const driver = ride.driver || {}; // Utiliser un objet vide si ride.driver est null/undefined
      const driverPhoto =
        driver.photo !== undefined ? driver.photo : "default_user.png"; // URL par défaut
      const driverPseudo = driver.pseudo ?? "N/A"; // Pseudonyme par défaut
      const driverRating = driver.rating ?? 0; // Note par défaut

      html += `
             <div class="card mb-3">
                 <div class="card-body">
                     <div class="row align-items-center">
                         <div class="col-md-2 text-center">
                             <img src="${driverPhoto}" class="rounded-circle" alt="${driverPseudo}" width="60">
                             <p class="mb-0">${driverPseudo}</p>
                             <p class="small">Note: ${driverRating}</p>
                         </div>
                         <div class="col-md-7">
                             <p><strong>Départ :</strong> ${
                               document.getElementById("departure-ride").value
                             } à ${formattedDepartureTime}</p>
                             <p><strong>Arrivée :</strong> ${
                               document.getElementById("arrival-ride").value
                             } à ${formattedArrivalTime}</p>
                             <p><strong>Places restantes :</strong> ${
                               ride.availableSeats ?? "N/A"
                             }</p> <!-- Ajouter un défaut si availableSeats est null -->
                             <p><strong>Prix :</strong> ${
                               ride.price ?? "N/A"
                             } €</p> <!-- Ajouter un défaut si price est null -->
                             <!-- Ajouter ici d'autres détails si nécessaire -->
                         </div>
                         <div class="col-md-3 text-end">
                             <a href="${
                               ride.detailsLink ?? "#"
                             }" class="btn btn-primary">Détails</a> <!-- Ajouter un lien vers les détails du covoiturage -->
                         </div>
                     </div>
                 </div>
             </div>
         `;
      console.log("DEBUG: HTML appended for one ride."); // Log 7
    }); // <--- Fin de la boucle forEach

    // Le code après la boucle forEach s'exécute UNE FOIS après que toutes les itérations soient terminées

    console.log(
      "DEBUG: forEach loop finished. Final HTML string length:",
      html.length
    ); // Log 8
    console.log("DEBUG: Final HTML string content:", html); // Log 9 - Inspect this carefully!

    rideResultsDiv.innerHTML = html; // Mettre le HTML complet dans le DOM
    console.log("DEBUG: rideResultsDiv.innerHTML is set."); // Log 10

    console.log("DEBUG: displayRideResults finished."); // Log 11
  }
});
