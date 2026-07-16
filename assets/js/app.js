function app() {
  return {
    lang: 'fr', 
    texts: {},
    init() { this.loadLang() },
    loadLang() {
      fetch(`lang/${this.lang}.json`)
        .then(res => res.json())
        .then(data => this.texts = data)
    },
    changeLang(newLang) { 
      this.lang = newLang
      this.loadLang()
    }
  }
}
