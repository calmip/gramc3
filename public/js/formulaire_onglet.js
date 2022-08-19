


$(document).ready(function() {

    $('.invisible_if_no_js').show();

    $('#onglets').tabs( {
    classes: {
        "ui-tabs": "highlight"
    }
    });

    // Lorsque je clique sur un lien #machin, activer l'onglet correspondant
    // Prérequis = Les div constituant les onglets ont comme id: #tab1, #tab2 etc.
    $('.gerer_onglets').click( function() {
    idcible = $(this).attr("href");
    tab = $(idcible).parents("div.onglet").attr("id");

    // tab3 ==> index 2 (zero-based !)
    tab_index = tab.slice(tab.length-1) - 1;
    $( '#onglets' ).tabs( "option", "active" , tab_index );
    });

    // Le dialog js utilisé suite à un appel ajax
    enregistrer_message = $( "#enregistrer_message" ).dialog({autoOpen: false,
            height: 300,
            width: 600,
            modal: true,
        buttons: {
               Ok: function() {
                 $( this ).dialog( "close" );
               }
                  }
    });

    // Lorsqu'on clique sur le bouton Enregistrer, on déclenche une requête ajax
    $('#form_enregistrer').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Récupère les données du formulaire, ajouter ENREGISTRER car ça ne se fait pas tout seul
        // (à cause de stopPropagation ou de preventDefault)
        form = $('#form_projet');
        form_data = form.serializeArray();
        //form_data.push({name:'ENREGISTRER',value:1});
        h = document.URL;
        //h += '&ajax=1';
        //console.log(h);
        //console.log(form.serialize());

        $.ajax(
        {
            type: 'POST',
            url: h,
            data: form_data,
            processData: true,
            success: function( data )
            {
                //console.log(data);
                msg = $.parseJSON(data);
                if ( msg.match(/ERREUR/) != null) {
                    msg = '<div class="message erreur"><h2>ATTENTION !</h2>'+msg+'</div>';
                } else {
                    msg='<div class="message info"><h2>Projet enregistré</h2>'+msg+'</div>';
                }
                enregistrer_message.html(msg);
                enregistrer_message.dialog("open");

                // Supprime les lignes des collaborateurs supprimés
                supprime_aff_collabs();
                $('#liste_des_collaborateurs').find("input[id$='_mail'][type='text']" ).each(function() {
                    if ($(this).val() != "" ) {
                        //alert($(this).val());
                        $(this).prop("disabled",true).attr("title","Vous ne pouvez plus changer l'adresse de courriel !");
                    }
                });
            },
            error: function(response)
            {
                alert("ERREUR ! Pas possible d'enregistrer !");
            }
        });
    });

    // Lorsqu'on clique sur le bouton nogenci, on remplit quelques champs
    $('#nogenci').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#form_prjGenciCentre').val('aucun');
        $('#form_prjGenciMachines').val('N/A');
        $('#form_prjGenciHeures').val('N/A');
        $('#form_prjGenciDari').val('N/A');
    });
} );


