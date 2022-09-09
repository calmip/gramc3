$(document).ready(

    function()
    {
        // collection = Le div qui entoure tout le tableau des collaborateurs
        //              En pratique il n'y en a qu'un 
        $(".collection").each(function()
        {
            // Les collaborateurs ne sont plus supprimés tout de suite mais plutôt marqués comme deleted
            // Du coup on n'utilise plus cette fonction
            // supprime_aff_collabs();
            // ajout d'un compteur des lignes
            if(  $(".collection-contents",this).data("count") == 0 )
            {
                $(".collection-contents",this).data("count", $(".collection-tbody",this).find('tr').length);
            };
        
            // ajout d'un paramètre id unique au bouton
            $(this).append('<button class="add" id="' + $(this).parent().parent().attr('id') +
                            '_add" type="submit" title="Ajouter une ligne">+</button>');
            $(this).find(".collection-tbody-old").find("input[id$='_mail'][type='text']")
                .attr("class","mail ui-autocomplete-input").prop('disabled', true);
            $(this).find(".collection-tbody-old").find("input[id$='_prenom'][type='text']")
                .attr("class","mail ui-autocomplete-input").prop('disabled', true);
            $(this).find(".collection-tbody-old").find("input[id$='_nom'][type='text']")
                .attr("class","mail ui-autocomplete-input").prop('disabled', true);
        
            // Le controleur envoie automatiquement une nouvelle ligne
            //nouvelle_ligne($(this));
            add_autocomplete( $(this) ); // ajout d'autocomplete sans nouvelle ligne
        });
        
        $(".collection .add").click(function(event)
        {
            event.preventDefault();
            nouvelle_ligne( $(this) );
        });
        
        $(".collection .resp").find("input[id$='_delete'][type='checkbox']").prop('disabled', true); // impossible de supprimer le responsable
        $(".collection .resp").find("input[id$='_delete'][type='checkbox']").css("opacity",'0'); // impossible de supprimer le responsable
        
        $(".collection .add").each( function() { opacity( $(this) ); }); // ajout du comportement du bouton delete à toutes les lignes
    }); // $(document).ready()

    function nouvelle_ligne(context)
    {
        var longueur = $(".collection-contents",context.parent()).data("count");
        var prototype = $(".collection-contents",context.parent()).data("prototype");
        prototype = prototype.replace(/__name__/g, longueur);
        $(".collection-contents",context.parent()).data("count", longueur + 1);
        $(".collection-tbody",context.parent()).append(prototype);
    
        $(".collection-tbody .collection-tbody-new",context.parent())
            .find("input[id$='_mail'][type='text']").attr("class","mail ui-autocomplete-input");
        opacity( context );// ajout du comportement du bouton delete à la nouvelle ligne
        //alert('before');
        mail_autocomplete(context); //autocomplete de l'adresse mail
    }

    function add_autocomplete(context)
    {
        $(".collection-tbody .collection-tbody-new",context.parent())
            .find("input[id$='_mail'][type='text']").attr("class","mail ui-autocomplete-input");
        opacity( context );// ajout du comportement du bouton delete à la nouvelle ligne
        //alert('before');
        mail_autocomplete(context); //autocomplete de l'adresse mail
    }

///////////////////////

    function opacity(context)
    {
    
        //$(".collection-contents .collection-tbody-new input[id$='_mail'][type='text']", context.parent()).each( function()
        //    { reactive_ligne( $(this) ); });
    
        //$(".collection-contents .collection-tbody-new input[id$='_mail'][type='text']", context.parent()).unbind('on').on('propertychange input', function()
        //    { reactive_ligne( $(this) ); })
    
        $("input[id$='_mail'][type='text']", context.parent()).unbind('blur').on('blur', function()
            { complete_ligne( $(this).val(), $(this) ); })
        
        // Lorsqu'on décoche une case login, on se choppe un message !
        $("input[id$='_login'][type='checkbox']", context.parent() .parent() ).unbind('change')
        .change(function()
        {
            //alert("coucou");
            if( !$(this).prop('checked') )
            {
                prenom = $(this).parent().parent().find("input[name*='[prenom]']").val();
                nom    = $(this).parent().parent().find("input[name*='[nom]']").val();
                if( nom == '' && prenom == '' ) nom = "cet utilisateur";
    
                // Ouvre un dialogue lorsqu'on clique sur "login" pour Décocher la case
                var case_login = $(this);
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
                var case_suppr = $(this);
                $("#dialog-suppression").html('<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span><strong>ATTENTION</strong> - Si vous supprimez '+prenom+' '+nom+' et s\'il a un compte, celui-ci sera fermé. OK ?</p>');
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
    } // function opacity()

////////////////////////////////////////

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

// ajax pour compléter la ligne
    function complete_ligne( mail, context )
    {
        let regEmail=/^[0-9a-z._-]+@[0-9a-z.-][0-9a-z.-]+[.][a-z][a-z]+$/i;
        let regData = /reallynouserrrrrrrr/;
    
        $.ajax
            ({
                url: $(".collection-contents").data("mail_autocomplete"),
                       type: "POST",
                       dataType: "json",
                       data: { 'Individu' : { 'mail' :  mail } }, // structure compatible symfony
                       context: context,
                       converters: { 'text json': true},
            })
        .done(
            function(data)
            {
                //alert( data );
                // Si le mail existe déjà sur gramc
                if(! regData.test( data ) )
                {
                    let input = '<div>' + data + '</div>';
                    $("[id$='_prenom']", context.parent().parent() ).val( $('#Individu_prenom', input).val());
                    $("[id$='_nom']", context.parent().parent() ).val($('#Individu_nom', input).val());
                    $("[id$='_statut']", context.parent().parent() ).val( $('#Individu_statut', input).val());
                    $("[id$='_laboratoire']", context.parent().parent()).val( $('#Individu_laboratoire', input).val());
                    $("[id$='_etablissement']", context.parent().parent() ).val( $('#Individu_etablissement', input).val());
                    $("[id$='_id']", context.parent().parent() ).val( $('#Individu_id', input).val());
                    $("[id$='_mail'][type='text']", context.parent().parent() ).prop("disabled",true);
                    $("[id$='_prenom']", context.parent().parent() ).prop("disabled",true);
                    $("[id$='_nom']", context.parent().parent() ).prop("disabled",true);
                }

                // Il faut créer le compte
                else
                {
                    let mail = context.val();
                    if (regEmail.test(mail))
                    {
                        if (NOINVITATION != 0)
                        {
                            msg = "ERREUR ! Pas possible d'enregistrer ! \n";
                            msg += "Vous avez peut-être ajouté un collaborateur inconnnu, il faut dans ce cas qu’il se connecte à cette plateforme pour y créer un compte avant de pouvoir être ajouté en tant que collaborateur. \n";
                            msg += "Attention : les demandes de compte sur le calculateur se font encore au moyen de formulaires séparés (https://www.criann.fr/acces-calcul/)";
                            alert(msg);
                            context.val("");
                        }
                        else
                        {
                            let msg =  "Afin de confirmer cette adresse de courriel, une invitation sera envoyée à votre collaborateur ";
                            msg += " lorsque vous enregistrerez cette page";
                            let html = '<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span>';
                            html += msg;
                            html += '</p>';
                            //alert(msg);
                            $("#dialog-invitation").html(html);
                            $("#dialog-invitation").dialog({
                                resizable: false,
                                height: "auto",
                                width: 400,
                                modal: true,
                                buttons: {
                                    "OK": function() {
                                        $( this ).dialog( "close" );
                                    }
                                }
                            });
                        }
                    }
                    else if (mail != "")
                    {
                        alert(mail + " n'est pas une adresse électronique valide");
                        context.val("");
                    }
                }
            })
        .fail(function(xhr, status, errorThrown) { alert ('Erreur complete_ligne ' + status + xhr); });
    }

    // A JETER
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

// A JETER
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

