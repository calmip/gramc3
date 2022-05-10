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
    
    //Récupérer les li du "En voir plus..." cliqué contenant la classe "priorite2"
    let prio2 = more_menu.parent().children('.priorite2')

    //Déclaration de la variable flg qui sert à déclarer lorsqu'il y a plus d'un li contenant la classe "priorite2"
    let flg = false
    
    prio2.each(function(){
        this.style.display = 'none'
        flg = true
    })

    //S'il y a un li contenant la classe "priorite2", afficher "En voir plus..."
    if(flg){
        more_menu.each(function(){this.style.display = 'block'})
    }

    //Récupérer "En voir plus..." du parent cliqué
    let ensavoirplus = more_menu.parent().children('.more')

    
    ensavoirplus.on('click', function(){
        if(this.classList.contains('more')){ 
            prio2.each(function(){
                this.style.display = 'initial'
            })
            this.innerHTML = 'En voir moins...'
            this.classList.add('less')
            this.classList.remove('more')
        } 
        else if(this.classList.contains('less')){
            prio2.each(function(){
                this.style.display = 'none'
            })
            this.innerHTML = 'En voir plus...'
            this.classList.add('more')
            this.classList.remove('less')
        }
    })
	
})