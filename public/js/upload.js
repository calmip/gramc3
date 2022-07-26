// Téléversement d'un fichier en utilisant le javascript:
// jquery-upload-file-master
//

$(document).ready(function() 
{
    // Téléversement de fichiers attachés: images, documents, etc.
    $(".fileuploader").each(function(){
        let fupl = $(this);
        let url = $(this).children("a").attr("href");
        $(this).uploadFile(
        {
            url: url,
            fileName: "fichier[fichier]",
            onSuccess:function(files,data,xhr,pd) 
            {
                uls = fupl.siblings(".uploadstatus")[0];
                acont = fupl.siblings(".ajax-file-upload-container")[0];
                json_data = $.parseJSON(data);
                if (json_data['OK']) 
                {
                    $(uls).html("<p>Le fichier est correctement enregistré.</p>")
                          .addClass("ok")
                          .removeClass('error')
                          .addClass("information");

                    // Si c'est une image, on essaie de l'afficher !
                    if (json_data['properties'])
                    {
                        id_div_img = "#" + json_data['properties']['name'];
                        div_image = $(id_div_img);
                        remover = div_image.parent();
                        remover = remover.find("a");
                        if (div_image)
                        {
                            html = '<img class="figure_image" src="data:image/jpg;base64,' + json_data['properties']['contents'] + '" ';
                            html += ' alt="Figure {{i}}" >';
                            div_image.html(html);
                            remover.show();
                        }
                    }
                }
                else 
                {
                    msg = json_data['message'];
                    $(uls).html("<p>" + msg + "</p>")
                          .addClass("error")
                          .removeClass("ok")
                          .addClass("information");
                }
                $(acont).remove();
            },
            dragDropStr: "</br><span><b>glissez - déposez</b></span>",
            uploadStr:"Téléversez"
        });
    });

    // Suppression de fichiers attachés: images, documents, etc.
    function supprimer_image(){
        event.preventDefault();
        let h = $(this).data('href');
        $.ajax({url: h,
                type: "GET",
                context: $(this)})
         .done(function(data){
            json_data = $.parseJSON(data);
            filename = json_data.split(' ')[1];
            id_div_img = "#" + filename;
            div_image = $(id_div_img);
            remover = div_image.parent();
            remover = remover.find("a");
            if (div_image)
            {
                html = '<img class="figure_image" src="toto.jpg">';
                div_image.html(html);
            }
            remover.hide();
         })
         .fail(function(xhr, status, errorThrown) { alert (errorThrown); });
    };
        
    $(".fileremover").click(supprimer_image);
});

