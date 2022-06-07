// Téléversement d'un fichier en utilisant le javascript:
// jquery-upload-file-master
//

$(document).ready(function() 
{
	url=$("#fileuploader").children("a").attr("href");
	$("#fileuploader").uploadFile(
	{
		url: url,
        fileName: "fichier[fichier]",
		//returnType: "json",
		//dynamicFormData: function() 
		//{
        //    var data = { "fichier" : {} };
		//	return data;
		//},
		onSuccess:function(files,data,xhr,pd) 
		{
			//msg=JSON.stringify(data);
            msg = data;
            //alert ( JSON.stringify(msg) );
			if (msg == 'OK') 
			{
				$('#uploadstatus').html("<p>Le fichier est correctement enregistré.</p>").addClass("info").addClass("message");
				$('#fileuploader').remove();
				$('#uploadform').remove();
			} else 
			{
				//alert ( JSON.stringify(msg) );
				$('#uploadstatus').html('<p>'+msg+'</p>').addClass("erreur").addClass("message");
			}
		},
		dragDropStr: "</br><span><b>Faites glisser et déposez le fichier</b></span>",
		uploadStr:"Téléversez votre description scientifique"
	});
});

