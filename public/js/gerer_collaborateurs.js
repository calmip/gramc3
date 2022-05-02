var NOMODIF = true;



$(document).ready(function()
{

    //var ht = "<body><p>Paragraph Here</p></body>";
    //alert( $('p', '<div>' + ht + '</div>').text() );
    
    // collection = la class du div qui encadre tout
    $(".collection").each(function()
    {
        // Les collaborateurs ne sont plus supprimés tout de suite mais plutôt marqués comme deleted
        // Du coup on n'utilise plus cette fonction
        // supprime_aff_collabs();
    
        // ajout d'un compteur de lignes
        if( $(".collection-contents",this).data("count") == 0 )
        {
            $(".collection-contents",this).data("count", $(".collection-tbody",this).find('tr').length);
        }
    
        // Bouton pour ajouter une ligne au formulaire
        $(this).append('<button class="add" id="' + $(this).parent().parent().attr('id') +
                       '_add" type="submit">Ajouter une ligne dans le formulaire</button>');
    
        // Désactiver les champs de l'adresse de mail, sauf le champ vide
        // On peut saisir un mail on ne peut pas modifier un mail existant
        $(this).find(".collection-tbody-old").find("input[id$='_mail'][type='text']")
            .attr("class","mail ui-autocomplete-input").prop('disabled', true)
            .attr("title","Vous ne pouvez pas modifier l'adresse courriel de vos collaborateurs");
    
        // Le controleur envoie automatiquement une nouvelle ligne
        // En fait non
        //nouvelle_ligne($(this));
    
        // ajout d'autocomplete sur le champ de mail
        add_autocomplete( $(this) );
    });

    // Le bouton d'ajout de ligne écoute le click
    $(".collection .add").click(function(event)
    {
        event.preventDefault();
        nouvelle_ligne( $(this) );
    });

    // impossible de supprimer le responsable: on désactive le bouton et on rend opaque le fond
    $(".collection .resp").find("input[id$='_delete'][type='checkbox']").prop('disabled', true);
    $(".collection .resp").find("input[id$='_delete'][type='checkbox']").css("opacity",'0');

    // ajout du comportement du bouton delete à toutes les lignes
    $(".collection .add").each( function() { supprime_collab( $(this) ); });

}); // $(document).ready()

// Ajoute une nouvelle ligne - Appelé par le bouton adhoc
function nouvelle_ligne(context)
{
    let longueur = $(".collection-contents",context.parent()).data("count");
    let prototype = $(".collection-contents",context.parent()).data("prototype");
    prototype = prototype.replace(/__name__/g, longueur);
    $(".collection-contents",context.parent()).data("count", longueur + 1);
    $(".collection-tbody",context.parent()).append(prototype);

    $(".collection-tbody .collection-tbody-new",context.parent())
       .find("input[id$='_mail'][type='text']").attr("class","mail ui-autocomplete-input");

    // ajout du comportement du bouton delete et mise en place de l'autocomplete
    supprime_collab( context );
    mail_autocomplete(context);
}

// Ajout de la fonctionnalité d'autocomplete sur le champ de mail
// TODO - Ne comprends rien !
function add_autocomplete(context)
{
    $(".collection-tbody .collection-tbody-new",context.parent())
       .find("input[id$='_mail'][type='text']").attr("class","mail ui-autocomplete-input");
    supprime_collab( context );// ajout du comportement du bouton delete à la nouvelle ligne
    mail_autocomplete(context); //autocomplete de l'adresse mail
}

// Exécuté lorsqu'on clique sur le bouton pour supprimer une ligne ou supprimer un login
function supprime_collab(context)
{
  //  $(".collection-contents .collection-tbody-new input[id$='_mail'][type='text']", context.parent()).each( function()
   //     { reactive_ligne( $(this) ); });

 //   $(".collection-contents .collection-tbody-new input[id$='_mail'][type='text']", context.parent()).unbind('on').on('propertychange input', function()
  //      { reactive_ligne( $(this) ); })

    // On essaie de compléter la ligne dès qu'on quitte le focus
    $("input[id$='_mail'][type='text']", context.parent()).unbind('blur').on('blur', function()
        { complete_ligne( $(this).val(), $(this) ); })


    // Lorsqu'on décoche une case login, on se choppe un message !
    $("input[id$='_login'][type='checkbox']", context.parent() .parent() ).unbind('change').change(function()
    {
        //alert("coucou");
        if( !$(this).prop('checked') )
        {
            prenom = $(this).parent().parent().find("input[name*='[prenom]']").val();
            nom    = $(this).parent().parent().find("input[name*='[nom]']").val();
            if( nom == '' && prenom == '' ) nom = "cet utilisateur";

            // Ouvre un dialogue lorsqu'on clique sur "login" pour Décocher la case
            let case_login = $(this);
            $("#dialog-collaborateur").html('<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span><strong>ATTENTION</strong> - Si vous décochez la case login de '+prenom+' '+nom+', son compte sera fermé. OK ?</p>');
            $("#dialog-collaborateur").dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    "OK": function() {
                        $( this ).dialog( "close" );
                    },
                    "NOOON !": function() {
                        case_login.prop('checked',true);
                        $( this ).dialog( "close" );
                    }
                }
            });
        };
    });

    // Lorsqu'on coche une case Suppression, on se choppe un message !
    $("input[id$='_delete'][type='checkbox']", context.parent() .parent() ).unbind('change')
    .change(function()
    {
        if( $(this).prop('checked') )
        {
            //$(this).parent().parent().find(":not([id$='_delete']):not([id$='_id'])").prop('disabled', true);
            //$(this).parent().parent().find("[id$='_delete'],[id$='_id']").prop('disabled', false);
            prenom = $(this).parent().parent().find("input[name*='[prenom]']").val();
            nom    = $(this).parent().parent().find("input[name*='[nom]']").val();
            if( nom == '' && prenom == '' ) nom = "cet utilisateur";

            // Ouvre un dialogue lorsqu'on clique sur "Supprimer collaborateur"
            let case_suppr = $(this);
            msg = '<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span><strong>ATTENTION</strong> - Si vous supprimez '+prenom+' '+nom+' et s\'il a un compte, celui-ci sera fermé. OK ?</p>';
            $("#dialog-suppression").html(msg);
            $("#dialog-suppression").dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    "OK": function() {
                        $( this ).dialog( "close" );
                    },
                    "NOOON !": function() {
                        case_suppr.prop('checked',false);
                        $( this ).dialog( "close" );
                    }
                }
            });
            //alert('ATTENTION ! Voulez-vous vraiment supprimer '+prenom+' '+nom+' de la liste des collaborateurs ?');
          };
 
       // else
       // {
       //     $(this).parent().parent().find(":not([id$='_mail'])").prop('disabled', false);
       //     $(this).parent().parent().find("[id$='_mail']").each( function() {complete_ligne( $(this).val(), $(this) );});
       //     };
    
    });
} // function supprime_collab()

// Fonction appelée lorsqu'on commence à entrer quelque chose dans le champ mail
function mail_autocomplete(context)
{
    //alert('autocomplete');
    $('.mail',context.parent() ).unbind('autocomplete').autocomplete(
    {
        delay: 500,
        minLength : 4,
        source : function(requete, reponse)
        {
            $.ajax({
                   url: $(".collection-contents").data("mail_autocomplete"),
                   type: "POST",
                   dataType: "json",
                   data: { 'autocomplete_form' : { 'mail' : requete.term } }, // structure compatible symfony
                   context: $(this)
                   })
            .done(function(data) { reponse(data); })
            .fail(function(xhr, status, errorThrown) { alert (errorThrown); });
        },
        select :  function(event, ui ) {complete_ligne( ui.item.value, $(this) );}
    });
} // function mail_autocomplete()

// ajax pour compléter la ligne lorsqu'on a fini de rentrer un mail
function complete_ligne( mail, context )
{
    //alert($(".collection-contents").data("mail_autocomplete"));
     $.ajax({
        url: $(".collection-contents").data("mail_autocomplete"),
               type: "POST",
               dataType: "json",
               data: { 'Individu' : { 'mail' :  mail } }, // structure compatible symfony
               context: context,
               converters: { 'text json': true},
               })
        .done(function(data)
        {
            //alert( data );
            let regex = /reallynouserrrrrrrr/;
            if( ! regex.test( data ) )
            //if( data != "nouser" )
            {
                let input = '<div>' + data + '</div>';
                /*$("input[id$='_prenom']", context.parent().parent() ).val( $('#Individu_prenom', input).val() ).prop('disabled', true) ;
                $("input[id$='_nom']", context.parent().parent() ).val( $('#Individu_nom', input).val() ).prop('disabled', true) ;
                $("input[id$='_statut']", context.parent().parent() ).val( $('#Individu_statut', input).val() ).prop('disabled', true) ;
                $("input[id$='_laboratoire']", context.parent().parent() ).val( $('#Individu_laboratoire', input).val() ).prop('disabled', true) ;
                $("input[id$='_etablissement']", context.parent().parent() ).val( $('#Individu_etablissement', input).val() ) .prop('disabled', true);
                $("input[id$='_id']", context.parent().parent() ).val( $('#Individu_id', input).val() ).prop('disabled', false) ;
                $("input[id$='_mail'][type='text']", context.parent().parent() ).prop("disabled",true)
                .attr("title","Vous ne pouvez plus changer l'adresse de courriel !");
                */
                $("input[id$='_mail'][type='text']", context.parent().parent() ).prop("disabled",true).attr("title", "invitation envoyée").val("Invitation envoyée à " + mail);
            }
            
            else
            {
                if (NOMODIF==true)
                {
                    $(this).val("Invitation envoyée à " + mail).prop("disabled",true).attr("title", "invitation envoyée");
                    //alert("Cet utilisateur est inconnu");
                }
                else
                {
                   $("input[id$='_delete'][type='checkbox']", context.parent().parent() )
                    .not(":checked").parent().parent().find("input[id$='_mail'][type='text']").prop("disabled",false)
                    .attr("title","Vous pouvez encore changer l'adresse de courriel !");
                }
            }
        })
        .fail(function(xhr, status, errorThrown) { /* alert ('Erreur complete_ligne ' + status + xhr); */ });
}

//////////////////////////////////////////////

function reactive_ligne(context)
{
    if( context.val() == '' )
    {
        // le champ mail est vide
        context.parent().parent().find(":not(input[id$='_mail'][type='text'])").prop("disabled",true)
            .attr("title","Commencez par l'adresse de courriel !");
        context.parent().parent().find(":not([disabled='true'])").attr("title","Commencez par l'adresse de courriel !");
    }
    else
    {
        // le champs mail contient quelque chose mais ce n'est pas à cause d'autocomplete
        context.parent().parent().find(":not(input[id$='_mail'][type='text'])").prop("disabled",false).attr("title","Vous pouvez remplir ce champ !");
        //context.parent().parent().find("input[id$='_prenom'][type='text']").prop("disabled",false).attr("title","CHAMP OBLIGATOIRE");
        //context.parent().parent().find("input[id$='_nom'][type='text']").prop("disabled",false).attr("title","CHAMP OBLIGATOIRE");
        context.parent().parent().find("input[id$='_prenom']").prop("disabled",false).attr("title","CHAMP OBLIGATOIRE !");
        context.parent().parent().find("input[id$='_nom']").prop("disabled",false).attr("title","CHAMP OBLIGATOIRE !");
        context.parent().parent().find("select").prop("disabled",false).attr("title","Vous pouvez remplir ce champ !");
        context.parent().parent().find("select").find("option").prop("disabled",false);
    }
}

function supprime_aff_collabs()
{
    $(".collection").find("input[id$='_delete'][type='checkbox']").each(function ()
    {
        if ($(this).is(':checked'))
        {
            //alert('dégage');
            $(this).parents('tr').remove();
        }
    });
}

