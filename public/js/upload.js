// Téléversement d'un fichier en utilisant le javascript:
// jquery-upload-file-master
//

$(document).ready(function() 
{
	//url=$("#fileuploader").children("a").attr("href");
    $(".fileuploader").each(function(){
        let url = $(this).children("a").attr("href");
        $(this).uploadFile(
        {
            url: url,
            fileName: "fichier[fichier]",
            //returnType: "json",
            //dynamicFormData: function() 
            //{
            //    var data = { "fichier" : {} };
            //return data;
            //},
            onSuccess:function(files,data,xhr,pd) 
            {
                //msg=JSON.stringify(data);
                msg = data;
                //alert(data);
                //alert ( JSON.stringify(msg) );
                if (msg == 'OK') 
                {
                    $('#uploadstatus').html("<p>Le fichier est correctement enregistré.</p>").addClass("ok").addClass("information");
                    $(this).remove();
                    //$('#uploadform').remove();
                }
                else 
                {
                    //alert ( JSON.stringify(msg) );
                    //$(this).html('<p>'+msg+'</p>').addClass("erreur").addClass("message");
                    $('#uploadstatus').html("<p>" + msg + "</p>").addClass("error").addClass("information");
                }
                $('#uploadstatus').remove();
            },
            dragDropStr: "</br><span><b>glissez - déposez</b></span>",
            uploadStr:"Téléversez"
        });
    });
});

