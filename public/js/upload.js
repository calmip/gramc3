// Téléversement d'un fichier en utilisant le javascript:
// jquery-upload-file-master
//

$(document).ready(function() 
{
    $(".fileuploader").each(function(){
        let fupl = $(this);
        let url = $(this).children("a").attr("href");
        $(this).uploadFile(
        {
            url: url,
            fileName: "fichier[fichier]",
            onSuccess:function(files,data,xhr,pd) 
            {
                msg = data;
                uls = fupl.siblings(".uploadstatus")[0];
                acont = fupl.siblings(".ajax-file-upload-container")[0];
                if (msg == 'OK') 
                {
                    $(uls).html("<p>Le fichier est correctement enregistré.</p>")
                          .addClass("ok")
                          .removeClass('error')
                          .addClass("information");
                }
                else 
                {
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
});

