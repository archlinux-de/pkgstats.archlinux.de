import ApiPackagesService from '@/js/services/ApiPackagesService'
import 'whatwg-fetch'

it('Popularity can be fetched', () => {
  ApiPackagesService.fetchPackagePopularity('nodejs')
    .then(data => expect(data).toBeGreaterThan(0))
})
