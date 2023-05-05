
// Convertit un string genre "1 234" en int
// l'espace peut être un &nbsp;
function str2int(s) {
    //alert ("str2int " + s);
    if (typeof(s) == 'string')
    {
        return parseInt(s.replace(/[^-0-9]/g,''));
    }
    else
    {
        return s;
    }
}

// convertit un int en string genre "1 234" si on a la bonne locale
// puis remplace les espaces par des &nbsp;
function int2str(i) {
    s = new Intl.NumberFormat().format(i);
    return s.replace(/[^-0-9]/g, '&nbsp;');
}

// Si on a un truc du genre: <td><span>1 234</span></td> renvoie 1234
// Si on n'a pas d'enfant, pareil
function elt2int(e)
{
    c = e.children();

    // si e n'a pas d'enfants on convertit le contenu de e en int
    if (c.length == 0)
    {
        return parseInt(e.html().replace(/[^-0-9]/g,''));
        //return e.html();
    }

    // si e a des enfants on continue de descendre
    else
    {
        return elt2int(c);
    }
}

// Met un int formatté dans l'enfant le plus profond
// Si négatif, met la classe attention à cet élément
function int2elt(i,e)
{
    c = e.children();

    // si e n'a pas d'enfants
    if (c.length == 0)
    {
        e.html(int2str(i));

        // Mettre la classe attention si i est <=0
        // sinon retirer la classe attention
        if (i<=0)
        {
            e.addClass('attention');
        }
        else
        {
            e.removeClass('attention');
        }
        return e;
    }

    // si e a des enfants on continue de descendre
    else
    {
        return int2elt(i,c);
    }
}

$(document).ready(function() {

    /* Pénalités */
    // Sera connecté au click des liens de pénalités
    function click_penalite (event ) {
        event.preventDefault();
        h = $(this).attr("href");
        $.ajax({url: h,
                type: "GET",
        dataType: "json",
                context: $(this)})
         .done(function(data){
             //alert ( data['penalite']);
            let recuperable = str2int(data['recuperable']);
            let penalite    = str2int(data['penalite']);

            ligne = $(this).parent().parent();
            // pas de formattage dans le tableau, sinon pb avec ce script et avec les tris
            ligne.children('td.penalite').html(penalite);
            ligne.children('td.recuperable').html(recuperable);
            ligne.find('.bouton_penalite').toggleClass('invisible');

            let quota = elt2int(ligne.children('td.quota'));
            let stats_penal        = elt2int($('#stats_penal')); //alert(stats_penal);
            let stats_recuperables = elt2int($('#stats_recuperables')); //alert(stats_recuperables);
            let stats_attribuees   = elt2int($('#stats_attribuees'));
            let stats_attribuables = elt2int($('#stats_attribuables')); 
            let attr               = elt2int(ligne.children('td.attr'));
    
            // Mise à jour des stats pénalités, attribution, et de la colonne attribution
            if (penalite == 0)
            {
                //alert (stats_penal);
                //alert (recuperable);
                stats_penal        -= recuperable;
                stats_recuperables += recuperable;
                attr               += recuperable;
                stats_attribuees   += recuperable;
                stats_attribuables  -= recuperable;
            }
            else
            {
                stats_penal        += penalite;
                stats_recuperables -= penalite;
                attr               -= penalite;
                stats_attribuees   -= penalite;
                stats_attribuables  += penalite;
            }

            $('#stats_penal').html(int2str(stats_penal));
            $('#stats_recuperables').html(int2str(stats_recuperables));
            $('#stats_attribuees').html(int2str(stats_attribuees));
            int2elt(stats_attribuables,$('#stats_attribuables'));

            ligne.children('td.attr').html(attr);
            //alert(attr);
            //alert(quota);
            if (attr == quota)
            {
                ligne.removeClass('alerte');
            }
            else
            {
                ligne.addClass('alerte');
            }
    
            //alert('recuperable=' + data['recuperable'] + ' ** ' + 'penalite=' + data['penalite']);
     })
         .fail(function(xhr, status, errorThrown) { alert (errorThrown); });
    };


    // Connecter aux fonctions click lors de l'initialisation
    $( "a.bouton_penalite" ).click(click_penalite);
});
