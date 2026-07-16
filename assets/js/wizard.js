function wizard() {
  return {
    step: 1,
    form: { 
      nom:'', prenom:'', email:'', pays:'', type:'', montant:'', adresse:'', codepostal:'' 
    },
    nextStep() { if(this.step<3) this.step++ },
    prevStep() { if(this.step>1) this.step-- },
    submitForm() {
      localStorage.setItem('morafinance', JSON.stringify(this.form))
      window.location.href = 'success.html'
    }
  }
}
