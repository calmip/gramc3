/*********
 * Remise à 0 et désactivation du champ #nb_heures_att
 * lorsque l'utilisateur presse le bouton-radio #refus
 *
 * Activation du champ #nb_heures_att lorsque l'utilisateur
 * presse le bouton-radio #valide
 *
 *************************/

$(document).ready(function(e) {
    $('#refus').click(function() {
		$('#nb_heures_att').prop('disabled',true);
		$('#nb_heures_att').val(0);
	});
    $('#valide').click(function() {
		$('#nb_heures_att').prop('disabled',false);
	});
});


// Réduire et développer le menu pour sauvegarder l'expertise d'un projet
$(document).ready(function() {

    $('#panneau_enregistrer .menu').on('click', function(){
        if(this.className == 'menu'){
            this.className = 'menu_ferme'
            this.parentNode.className = 'panneau_ferme'
			console.log(this.parentNode)
        }
        else{
            this.className = 'menu'
			this.parentNode.className = ''
        }
		console.log(this.parentNode)
    })
})