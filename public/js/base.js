$( document ).ready(function() {

    // Définition du bouton retour vers le haut
    let scrollTop = document.querySelector('#scrollTop')
    
    //Ecouteur du scroll de l'utilisateur
    window.addEventListener('scroll', ()=>{
        if(scrollY > 400){
            scrollTop.style.opacity = '1';
        }
        else{
            scrollTop.style.opacity = '0';
        }
    })

    scrollTop.addEventListener('click', function(){
        document.documentElement.scrollTop = 0;
    })
})


$( document ).ready(function() {
    //Récupérer "En voir plus..." 
    let more_menu = $('.more')

    // Y a-til un ou plusieurs "more menu" ?
    if (more_menu.length != 0)
    {
        let container = more_menu[0].parentNode.parentNode.parentNode.parentNode
            
        if(container.className != "section_administrateur" && container.className != "section_president")
        {
    
            // Dès qu'on a un li.priorite2, on ne l'affiche pas
            // 
            $('li.priorite2').each(function(){
                this.style.display = 'none'
                let more_locals = $(this).siblings('.more');
                if (more_locals.length > 0)
                {
                    more_local = more_locals[0];
                    more_local.style.display = 'block';
                }
            })
    
            // Binder les li "En voir plus..."
            let envoirplus = more_menu.parent().children('.more')
            envoirplus.on('click', function(){
                
                // Bouton En voir plus: on affiche les li de priorité 2 et on le transforme en "en voir moins"
                if(this.classList.contains('more')){
                    // On travaille sur les éléments de priorité 2 frères de l'élément cliqué
                    $(this).siblings('li.priorite2').each(function(){
                        this.style.display = 'initial'
                    })
                    this.innerHTML = 'En voir moins...'
                    this.classList.add('less')
                    this.classList.remove('more')
                }
                
                // Bouton en voir moins: on cache les li de priorité 2 et on le transforme en "en voir plus"
                else if(this.classList.contains('less')){
                    $(this).siblings('li.priorite2').each(function(){
                        this.style.display = 'none'
                    })
                    this.innerHTML = 'En voir plus...'
                    this.classList.add('more')
                    this.classList.remove('less')
                }
            })
        }
    }
})


$(document).ready(function() {
    
    //Changer la classe du panneau "Adinistrateur" pour le réduire en un point lors du click
    $('.role_admin').on('click', function(){
        if(this.className == 'role_admin_reduit'){
            this.className = 'role_admin'
        }
        else{
            this.className = 'role_admin_reduit'
        }
    })
})


function retourArriere(){
    this.on('click', function(){
        //history.back()
        console.log('eee')
})}
