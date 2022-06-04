describe('Start page', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('shows title', () => {
    cy.contains('h1', 'Arch Linux package statistics')
  })
})
